<?php

$targetTables = array(
	'ageRanges' => array('name' => 'ownAgerange', 'fields' => array('minAge', 'maxAge')),
	'boundingBoxes' => array('name' => 'ownBoundingbox', 'fields' => array('minLat', 'minLong', 'maxLat', 'maxLong')),
	'times' => array('name' => 'ownTime', 'fields' => array('dayOfWeek', 'startTime', 'endTime')),
	'genres' => array('name' => 'sharedGenres', 'ids' => true),
	'occupations' => array('name' => 'sharedOccupations', 'ids' => true),
	'programmes' => array('name' => 'sharedProgrammes', 'ids' => true)
);

function getTargets(&$bean, $type) {
	global $targetTables;
	if (!array_key_exists($type, $targetTables)) { return false; }

	$items = R::exportAll($bean->{$targetTables[$type]['name']});

	if (isset($targetTables[$type]['ids']) && $targetTables[$type]['ids']) {
		return array_map(function ($item) use ($targetTables, $type) {
			return $item['id'];
		}, array_values($items));
	} else {
		return array_map(function ($item) use ($targetTables, $type) {
			$r = array();
			array_walk($targetTables[$type]['fields'], function ($field) use ($item, &$r) {
				$r[$field] = $item[$field];
			});
			return $r;
		}, array_values($items));
	}
}
function getAllTargets(&$bean) {
	global $targetTables;
	$r = array();
	array_walk(array_keys($targetTables), function ($type) use (&$bean, &$r) {
		$r[$type] = getTargets($bean, $type);
	});
	return $r;
}
function campaignExists($id) {
	$bean = R::load('campaigns', $id);
	return $bean->id > 0;
}

$app->get('/campaigns(/)', function () use ($app) {
	$beans = R::find('campaigns');
	output_json(array_map(function ($bean) {
		return getCampaign($bean);
	}, array_values($beans)));
});

$app->get('/campaigns/:id', function ($id) use ($app) {
	$bean = R::load('campaigns', $id);
	if (!$bean->id) { notFound('Could not find campaign with that ID.'); }
	output_json(getCampaign($bean));
});

function getCampaign ($bean) {
	$campaign = $bean->export();
	$id = $campaign['id'];
	$campaign['adverts'] = array_keys($bean->sharedAdverts);
	$campaign['targets'] = getAllTargets($bean);
	$campaign['targets']['genders'] = preg_split('@,@', $campaign['gender'], NULL, PREG_SPLIT_NO_EMPTY); // don't allow set [""]
	unset($campaign['gender']);
	$campaign['targets']['schedules'] = preg_split('@,@', $campaign['schedule'], NULL, PREG_SPLIT_NO_EMPTY);
	unset($campaign['schedule']);

	return $campaign;
}

//$app->get('/campaigns/:id/adverts(/)', function ($id) use ($app) {
//	if (campaignExists($id)) { return notFound('Campaign not found.'); }
//	output_json(getAdverts($id));
//});

//$app->get('/campaigns/:id/targets(/)', function ($id) use ($app) {
//	if (campaignExists($id)) { return notFound('Campaign not found.'); }
//	output_json(getAllTargets($id));
//});

//$app->get('/campaigns/:id/targets/:type(/)', function ($id, $type) use ($app, $targetTables) {
//	if (!campaignExists($id)) { return notFound('Campaign not found.'); }
//	$r = getTargets($id, $type);	
//	if ($r === false) { return notFound('No such type.'); }
//	output_json($r);
//});

$app->put('/campaigns/:id', function ($id) use ($app) {
        $req = $app->request()->getBody();
        $campaign = R::load('campaigns', $id);
        setCampaign($campaign, $req);
	output_json(getCampaign($campaign));
});

$app->post('/campaigns(/)', function () use ($app) {
        $req = $app->request()->getBody();
        $campaign = R::dispense('campaigns');
        setCampaign($campaign, $req);
	output_json(getCampaign($campaign));
});

function setCampaign($campaign, $req) {
	$campaign->title = $req['title'];
	$campaign->startDate = $req['startDate'];
	$campaign->endDate = $req['endDate'];
	
	$campaign->gender = '';
	if (in_array('male', $req['targets']['genders'])) {
		$campaign->gender = 'male';
	}
	if (in_array('female', $req['targets']['genders'])) {
		if (count($campaign->gender)) { $campaign->gender .= ','; }
		$campaign->gender .= 'female';
	}

	$campaign->schedule = '';
	if (in_array('vod', $req['targets']['genders'])) {
		$campaign->schedule = 'vod';
	}
	if (in_array('live', $req['targets']['schedules'])) {
		if (count($campaign->schedule)) { $campaign->schedule .= ','; }
		$campaign->schedule .= 'live';
	}

    $campaignId = R::store($campaign);

	$campaign->sharedAdverts = array_map(function ($r) {
		return R::load('adverts', $r);
	}, ifsetor($req['adverts'], array()));

	$campaign->ownAgerange = array_map(function ($r) use ($campaignId) {
		$minAge = $r['minAge'];
		$maxAge = $r['maxAge'];
		$bean = R::findOne('agerange', ' minAge = ? AND maxAge = ? AND campaigns_id = ? ', array($minAge, $maxAge, $campaignId));
		if (!$bean) {
			$bean = R::dispense('agerange');
			$bean->minAge = $minAge;
			$bean->maxAge = $maxAge;
		}
		return $bean;
	}, ifsetor($req['targets']['ageRanges'], array()));

	$campaign->ownBoundingbox = array_map(function ($r) use ($campaignId) {
		$minLat = $r['minLat'];
                $maxLat = $r['maxLat'];
                $minLong = $r['minLong'];
                $maxLong = $r['maxLong'];
		$bean = R::findOne('boundingbox', ' minLat = ? AND maxLat = ? AND minLong = ? AND maxLong = ? ', array($minLat, $maxLat, $minLong, $maxLong));
		if (!$bean) {
      	   		$bean = R::dispense('boundingbox');
                	$bean->minLat = $r['minLat'];
	                $bean->maxLat = $r['maxLat'];
	                $bean->minLong = $r['minLong'];
	                $bean->maxLong = $r['maxLong'];
		}
                return $bean;
        }, ifsetor($req['targets']['boundingBoxes'], array()));

	$campaign->ownTime = array_map(function ($r) {
                $dayOfWeek = $r['dayOfWeek'];
		$startTime = $r['startTime'];
                $endTime = $r['endTime'];
		$bean = R::findOne('time', ' dayOfWeek = ? AND startTime = ? AND endTime = ? ', array($dayOfWeek, $startTime, $endTime));
		if (!$bean) {
	                $bean = R::dispense('time');
	                $bean->dayOfWeek = $r['dayOfWeek'];
			$bean->startTime = $r['startTime'];
	                $bean->endTime = $r['endTime'];
		}
                return $bean;
        }, ifsetor($req['targets']['times'], array()));

	$campaign->sharedGenres = array_map(function ($id) {
		return R::load('genres', $id);
	}, ifsetor($req['targets']['genres'], array()));
	
	$campaign->sharedOccupations = array_map(function ($id) {
		return R::load('occupations', $id);
	}, ifsetor($req['targets']['occupations'], array()));

	$campaign->sharedProgrammes = array_map(function ($id) {
		return R::load('programmes', $id);
	}, ifsetor($req['targets']['programmes'], array()));

	$campaignId = R::store($campaign);
	

}
