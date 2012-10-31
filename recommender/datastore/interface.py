import argparse
import datetime
from time import time
import random

import mysql.connector

from credentials import credentials
from util.calc_age import calc_age

def _field_string(fields):
	"""Returns '*' if `fields` is empty, otherwise returns a string of
	comma-separated elements surrounded by backticks"""
	return ','.join(['`'+f+'`' for f in fields]) or '*'

def _time_from_seconds(total_seconds):
	hours = total_seconds // 3600
	minutes = (total_seconds % 3600) // 60
	seconds = (total_seconds % 3600) % 60

	time_ = datetime.time(hours, minutes, seconds)
	return time_

# TODO: Only return from current campaigns.
def get_campaign_pool(uid, pid, when=None):
	if when is None:
		when = datetime.datetime.now()
	"""Given a user id, programme id and time, returns a pool of adverts whose
	campaigns allow the advert to be shown to the given user during the given
	programme at the given time. Advert ids are returned along with the
	campaign nichenesses."""
	query = (
		"SELECT `u`.`dob`, `u`.`gender`, `u`.`occupation`, `u`.`lat`, "
			"`u`.`long`, `p`.`id`, `p`.`channel`, `p`.`genre`, `p`.`live`, "
			"`c`.`schedule`, `c`.`gender`, `c_a`.`minAge`, `c_a`.`maxAge`, "
			"`c_b`.`minLong`, `c_b`.`maxLong`, `c_b`.`minLat`, `c_b`.`maxLat`, "
			"`c_g`.`genre`, `c_o`.`occupation`, `c_p`.`programme`, "
			"`c_t`.`dayOfWeek`, `c_t`.`startTime`, `c_t`.`endTime`, "
			"`c`.`id`, `c`.`nicheness`" #TODO: Return actual nicheness.
		"FROM `users` AS u, `programmes` as p, `campaigns` as c "
		"LEFT JOIN `campaignAgeRanges`     AS c_a ON `c`.`id`=`c_a`.`campaign` "
		"LEFT JOIN `campaignBoundingBoxes` AS c_b ON `c`.`id`=`c_b`.`campaign` "
		"LEFT JOIN `campaignGenres`        AS c_g ON `c`.`id`=`c_g`.`campaign` "
		"LEFT JOIN `campaignOccupations`   AS c_o ON `c`.`id`=`c_o`.`campaign` "
		"LEFT JOIN `campaignProgrammes`    AS c_p ON `c`.`id`=`c_p`.`campaign` "
		"LEFT JOIN `campaignTimes`         AS c_t ON `c`.`id`=`c_t`.`campaign` "
		"WHERE `u`.`id` = {id} "
		"AND `c`.`startDate` <= {when} "
		"AND {when} <= `c`.`endDate`").format(id=uid, when=when)
		# "AND `c_t`.`startTime` <= {when} "
		# "AND {when} <= `c_t`.`endTime` "

	conn = mysql.connector.connect(**credentials)
	cursor = conn.cursor()
	cursor.execute(query)

	adverts = {}
	for result in cursor:
		user = {}
		programme = {}
		restrict = {}
		(user['dob'], user['gender'], user['occupation'], user['lat'],
			user['long'], programme['id'], programme['channel'],
			programme['genre'], programme['live'], restrict['schedule'],
			restrict['gender'], restrict['minAge'], restrict['maxAge'],
			restrict['minLong'], restrict['maxLong'], restrict['minLat'],
			restrict['maxLat'], restrict['genre'], restrict['occupation'],
			restrict['programme'], restrict['dayOfWeek'], restrict['startTime'],
			restrict['endTime'], campaignid, nicheness) = result

		dt = datetime.datetime.utcfromtimestamp(when)
		restriction_match = {
			'schedule': lambda: programme['live'] in restrict['schedule'],
			'gender': lambda: user['gender'] in restrict['gender'],
			'minAge': lambda: calc_age(user['dob']) >= restrict['minAge'],
			'maxAge': lambda: calc_age(user['dob']) <= restrict['maxAge'],
			'minLong': lambda: user['long'] >= restrict['minLong'],
			'maxLong': lambda: user['long'] <= restrict['maxLong'],
			'minLat': lambda: user['lat'] >= restrict['minLat'],
			'maxLat': lambda: user['lat'] <= restrict['maxLat'],
			'genre': lambda: programme['genre'] == restrict['genre'],
			'occupation': lambda: user['occupation'] == restrict['occupation'],
			'programme': lambda: programme['id'] == restrict['programme'],
			'dayOfWeek': lambda: dt.isoweekday() == restrict['dayOfWeek'],
			'startTime': lambda: dt.time() >= _time_from_seconds(
												restrict['startTime'].seconds),
			'endTime': lambda: dt.time() <= _time_from_seconds(
													restrict['endTime'].seconds)
		}

		available = all([v is None or restriction_match[k]()
							for k, v in restrict.iteritems()])

		if available:
			adverts[campaignid] = float(nicheness)

	cursor.close()
	conn.close()

	return adverts

def get_ad(campaignId):
	query = (	"SELECT `id` "
				"FROM `campaignAdverts` "
				"WHERE `campaign` = {campaignId}".format(
					campaignId=campaignId))
				
	conn = mysql.connector.connect(**credentials)
	cursor = conn.cursor()

	cursor.execute(query)

	ads = [response[0] for response in cursor]

	cursor.close()
	conn.close()

	return random.choice(ads or [-1])


def get_upcoming_programmes(startTime=time(), lookahead=300):
	query = (	"SELECT `id`, `vector` "
				"FROM `programmes` "
				"WHERE `programmes`.`name` != 'Off air' "
				"AND `start` "
				"BETWEEN {start_time} "
				"AND {end_time}".format(
					start_time=int(startTime),
					end_time=int(startTime + lookahead)))

	conn = mysql.connector.connect(**credentials)
	cursor = conn.cursor()

	cursor.execute(query)

	channel_vectors = [(p_id, p_vector) for p_id, p_vector in cursor]

	cursor.close()
	conn.close()

	return channel_vectors


def get_programme(pid, fields=[]):
	"""Returns a tuple of the values of the given fields for a programme with
	the given id. If no fields are specified, all are returned."""
	query = (	'SELECT '+_field_string(fields)+' '
				'FROM `programmes` '
				'WHERE `id`='+str(pid))
	conn = mysql.connector.connect(**credentials)
	cursor = conn.cursor()
	cursor.execute(query)
	results = cursor.fetchall()[0][0]
	cursor.close()
	conn.close()
	return results

# TODO: Properly return entire list, and modify ALL files which use this (urgh...)
def get_user(userid, fields=[]):
	"""Returns a tuple of the values of the given fields for a user with
	the given id. If no fields are specified, all are returned."""
	query = (	'SELECT '+_field_string(fields)+' '
				'FROM `users` '
				'WHERE `id`='+str(userid))
	conn = mysql.connector.connect(**credentials)
	cursor = conn.cursor()
	try:
		cursor.execute(query)
	except mysql.connector.errors.ProgrammingError:
		cursor.close()
		conn.close()
		raise Exception("Error executing mysql query: {q}".format(q=query))
	results = cursor.fetchall()[0]
	cursor.close()
	conn.close()
	return results

def set_user(userid, fields=[], vals=[]):
	assert len(fields) == len(vals)

	changes = ', '.join("{}='{}'".format(e[0], e[1]) for e in zip(fields, vals))
	query = "UPDATE `users` SET {c} WHERE `id`={id}".format(c=changes, id=userid)

	conn = mysql.connector.connect(**credentials)
	cursor = conn.cursor()
	cursor.execute(query)
	conn.commit()
	cursor.close()
	conn.close()
