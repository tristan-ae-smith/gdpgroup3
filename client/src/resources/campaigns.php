<?php

$targetTables = array(
	'ageRanges' => array('table' => 'campaignAgeRanges', 'fields' => array('id', 'minAge', 'maxAge')),
	'boundingBoxes' => array('table' => 'campaignBoundingBoxes', 'fields' => array('id', 'minLat', 'minLong', 'maxLat', 'maxLong')),
	'genres' => array('table' => 'campaignGenres', 'fields' => array('id', 'genre')),
	'occupations' => array('table' => 'campaignOccupations', 'fields' => array('id', 'occupation')),
	'programmes' => array('table' => 'campaignProgrammes', 'fields' => array('id', 'programme'))
);

function getTargets($id, $type) {
	global $targetTables;
	if (!array_key_exists($type, $targetTables)) { return false; }

	$beans = R::find($targetTables[$type]['table'], ' campaign = ? ', array($id));

	return array_map(function ($bean) use ($targetTables, $type) {
		$r = array();
		array_walk($targetTables[$type]['fields'], function ($field) use ($bean, &$r) {
			$r[$field] = $bean->{$field};
		});
		return $r;
	}, array_values($beans));
}
function getAllTargets($id) {
	global $targetTables;
	$r = array();
	array_walk(array_keys($targetTables), function ($type) use ($id, &$r) {
		$r[$type] = getTargets($id, $type);
	});
	return $r;
}
function getAdverts($id) {
	$beans = R::find('campaignAdverts', ' campaign = ? ', array($id));
	return array_map(function ($bean) { return $bean->id; }, array_values($beans));
}
function campaignExists($id) {
	$bean = R::load('campaigns', $id);
	return $bean->id > 0;
}

$app->get('/campaigns/(:id)', function($id = null) use ($app) {
	if (!is_null($id)) {
		$r = R::find('campaigns', 'id = ?', array($id));
	} else {
		$r = R::find('campaigns');
	}

	$campaigns = array_map(function ($campaign) {
		$id = $campaign['id'];
		$campaign['gender'] = preg_split('@,@', $campaign['gender'], NULL, PREG_SPLIT_NO_EMPTY); // don't allow set [""]
		$campaign['schedule'] = preg_split('@,@', $campaign['schedule'], NULL, PREG_SPLIT_NO_EMPTY);
		$campaign['adverts'] = getAdverts($id);
		$campaign['targets'] = getAllTargets($id);
		return $campaign;
	}, R::exportAll($r));

	
	if (!is_null($id)) {
		if (count($campaigns) === 0) { return notFound('Could not find campaign with that ID.'); }
		output_json($campaigns[0]);
	} else {
		output_json($campaigns);
	}
});

$app->get('/campaigns/:id/adverts(/)', function ($id) use ($app) {
	if (campaignExists($id)) { return notFound('Campaign not found.'); }
	output_json(getAdverts($id));
});

$app->get('/campaigns/:id/targets(/)', function ($id) use ($app) {
	if (campaignExists($id)) { return notFound('Campaign not found.'); }
	output_json(getAllTargets($id));
});

$app->get('/campaigns/:id/targets/:type(/)', function ($id, $type) use ($app, $targetTables) {
	if (!campaignExists($id)) { return notFound('Campaign not found.'); }
	$r = getTargets($id, $type);	
	if ($r === false) { return notFound('No such type.'); }
	output_json($r);
});

$app->put('/campaigns/:id', function ($id) use ($app) {
        $req = $app->request()->getBody();
        $campaign = R::load('campaigns', $id);
        setCampaign($campaign, $req);
});

$app->post('/campaigns(/)', function () {
        $req = $app->request()->getBody();
        $campaign = R::dispense('campaigns');
        setCampaign($campaign, $req);
});

function setCampaign($campaign, $req) {
	$campaign->title = $req['title'];
	$campaign->startDate = $req['startDate'];
	$campaign->endDate = $req['endDate'];
	
	//$campaign->schedule = $req['schedule'];
	//$campaign->gender = $req['gender'];

	$campaign->sharedAdverts = array_map(function ($id) {
		return R::load('adverts', $id);
	}, $req['adverts']);

	$campaignId = R::store($campaign);
	
	//if (isset($req['adverts'])) {
	//	array_walk($req['adverts'], function ($advert) {
	//		if (!$advert['id']) {
	//			$campaignAdvert = R::dispense('campaignAdverts');
	//			$campaignAdvert->campaign = $campaignAdvert;
	//			$campaignAdvert->advert = $advert['advert'];
	//		}
	//	});
	//}
        output_json($campaign->export());

}
