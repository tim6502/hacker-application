<?php

require_once('stepg/stepgame.class.php');


/*

game_rec {

	turn_list {
		turn_id {
			turn_number

			attack_rune
			attack_points
			defend_rune
			defend_points

			turn_event_id
			play_event_id
			result_event_id
		}
	}

	event_list {
		event_id {
			kind: turn
			attack_player_id
		}
		event_id {
			kind: play
			attack_space_id
			defend_space_id
		}
		event_id {
			kind: result
			attack_success_ind = 1 | 0 | null

			attacker_rune
			defender_rune
		}
		repeat ...

		event_id {
			kind: game_over
			winner_player_id
		}
	}

}

*/



class Runez extends StepGame {

	const	BOARD_SQUARE = 1;
	const BOARD_HEX = 2;

	const ERR_INCORRECT_RUNE_COUNT = 1001;
	const ERR_NOT_PLAYER_SPACE = 1002;
	const ERR_NOT_OPPONENT_SPACE = 1003;
	const ERR_INVALID_SPACE = 1004;
	const ERR_NOT_ENOUGH_MANA = 1005;
	const ERR_NOT_ENOUGH_RUNES = 1006;
	const ERR_SPACES_NOT_CONNECTED = 1007;
	const ERR_MANA_COUNT_NOT_NUMERIC = 1008;




	//////////////////////////////////////////////////////////

	///////////////// debug routines /////////////////////

/*
	function getScore($player_id) {
		return $this->getPlayerLargestContiguousSpaceList($player_id);
	}
	function setOwners($owner_map) {
		$this->game_rec['state']['space_owner_map'] = $owner_map;
	}


	function setGameOver($game_over_ind = 0) {
		$this->keepAlive();

		$this->game_rec['state']['game_over_ind'] = $game_over_ind;
	}
*/

	function reset() {
		$this->keepAlive();

		$new_game_rec = $this->getNewGameRecord();
		arrayCopyFields($new_game_rec, $this->game_rec);
	}

	function getGameRec() {
		return $this->game_rec;
	}



	//////////////////////////////////////////////////////////

/*
board types:

rectangular
width, height

hexagonal
radius
-- 1 is just one hex so invalid (too small)
-- each "radius" unit is one more ring around the innermost hex

*/


	function newGame($raw_game_ident, $board_options = null) {
		$rst = parent::newGame($raw_game_ident);

		return $rst;
	}

	function keepAlive() {
		$this->game_active_flag = true;
		$this->game_rec['updated_dttm'] = $this->getCurrentDttm();
	}


	//////////////////////////////////////////////////////////


	function getCurrentTurnId() {
		$this->ready();

		if (!$this->gameStarted()) {
			$this->error(Runez::ERR_GAME_NOT_STARTED, 'The game has not started yet');
			return false;
		}

		if ($this->ended()) {
			$this->error(Runez::ERR_GAME_OVER, 'Game ended');
			return false;
		}

		return count($this->game_rec['turn_list']) - 1;
	}

	function getEvents($start_event_id) {
		$this->ready();

		if (!$this->gameStarted()) {
			$this->error(Runez::ERR_GAME_NOT_STARTED, 'The game has not started yet');
			return false;
		}

		if (!($start_event_id < count($this->game_rec['event_list'])) && $this->ended()) {
			$this->error(Runez::ERR_GAME_OVER, 'Event id invalid and game over');
			return false;
		}

		if ($start_event_id === null || $start_event_id == '0') {
			$event_list = $this->game_rec['event_list'];
		} else {
			$event_list = array_slice($this->game_rec['event_list'], $start_event_id);
		}

		return $event_list;
	}

	function playSetAttack($attack_player_id, $attack_space_id, $defend_space_id) {
		$this->ready();
		$this->keepAlive();

		$turn_id = $this->getCurrentTurnId();
		if ($turn_id === false) {
			return false;
		}

		if ($this->game_rec['turn_list'][$turn_id]['attack_player_id'] != $attack_player_id) {
			$this->error(Runez::ERR_NOT_PLAYER_TURN, 'Not your turn');
			return false;
		}

		if ($this->game_rec['turn_list'][$turn_id]['attack_space_id'] !== null) {
			$this->error(Runez::ERR_TURN_ALREADY_PLAYED, 'Attack has already been set');
			return false;
		}

		if (!$this->isSpaceIdValid($attack_space_id)) {
			$this->error(Runez::ERR_INVALID_SPACE, 'Invalid attack space');
			return false;
		}

		if (!$this->isSpaceIdValid($defend_space_id)) {
			$this->error(Runez::ERR_INVALID_SPACE, 'Invalid defend space');
			return false;
		}

		if (!$this->isPlayerSpace($attack_player_id, $attack_space_id)) {
			$this->error(Runez::ERR_NOT_PLAYER_SPACE, 'Player does not control attack space');
			return false;
		}

		if ($this->isPlayerSpace($attack_player_id, $defend_space_id)) {
			$this->error(Runez::ERR_NOT_OPPONENT_SPACE, 'Defend space does not belong to opponent');
			return false;
		}

		// check if spaces are adjacent
		if (!$this->areSpacesAdjacent($attack_space_id, $defend_space_id)) {
			$this->error(Runez::ERR_SPACES_NOT_CONNECTED, 'The defending space must be next to the attacking space');
			return false;
		}

		$defend_player_id = $this->getSpacePlayer($defend_space_id);

		$this->game_rec['turn_list'][$turn_id]['attack_space_id'] = $attack_space_id;
		$this->game_rec['turn_list'][$turn_id]['defend_space_id'] = $defend_space_id;
		$this->game_rec['turn_list'][$turn_id]['defend_player_id'] = $defend_player_id;

		$this->addEventAttack($turn_id, $attack_space_id, $defend_space_id, $defend_player_id);

		return true;
	}

	function playSetRuneMana($player_id, $rune, $mana_count) {
		$this->ready();
		$this->keepAlive();

		$turn_id = $this->getCurrentTurnId();
		if ($turn_id === false) {
			return false;
		}

		if ($this->game_rec['turn_list'][$turn_id]['attack_player_id'] == $player_id) {
			$role = 'attack';
		} else if ($this->game_rec['turn_list'][$turn_id]['defend_player_id'] == $player_id) {
			$role = 'defend';
		} else {
			$this->error(Runez::ERR_NOT_PLAYER_TURN, 'Not your turn');
			return false;
		}

		if ($this->game_rec['turn_list'][$turn_id]['attack_space_id'] === null) {
			$this->error(Runez::ERR_TURN_ALREADY_PLAYED, 'Attack has not been set');
			return false;
		}

		if ($this->game_rec['turn_list'][$turn_id][$role . '_rune'] !== null) {
			$this->error(Runez::ERR_TURN_ALREADY_PLAYED, 'Runes and mana already set');
			return false;
		}

		if (!$this->isRuneValid($rune)) {
			$this->error(Runez::ERR_INVALID_RUNE, 'Invalid rune type');
			return false;
		}

		if (!is_numeric($mana_count)) {
			$this->error(Runez::ERR_MANA_COUNT_NOT_NUMERIC, 'Mana count required');
			return false;
		}

		if ($this->game_rec['state']['player_table'][$player_id]['rune_' . $rune . '_count'] < 1) {
			$this->error(Runez::ERR_NOT_ENOUGH_RUNES, 'No runes left of that type');
			return false;
		}

		if ($this->game_rec['state']['player_table'][$player_id]['mana_count'] < $mana_count) {
			$this->error(Runez::ERR_NOT_ENOUGH_MANA, 'Not enough mana');
			return false;
		}

		$this->game_rec['state']['player_table'][$player_id]['rune_' . $rune . '_count']--;
		$this->game_rec['state']['player_table'][$player_id]['mana_count'] -= $mana_count;

		$this->game_rec['turn_list'][$turn_id][$role . '_rune'] = $rune;
		$this->game_rec['turn_list'][$turn_id][$role . '_mana_count'] = $mana_count;

		$this->resolveTurn($turn_id);

		return true;
	}

	protected function isRuneValid($rune) {
		return ($rune == 'sun' || $rune == 'moon' || $rune == 'earth');
	}
	protected function isRuneGreater($rune1, $rune2) {
		if ($rune1 == 'sun' && $rune2 == 'earth') {
			return true;
		} else if ($rune1 == 'moon' && $rune2 == 'sun') {
			return true;
		} else if ($rune1 == 'earth' && $rune2 == 'moon') {
			return true;
		}

		return false;
	}

	protected function resolveTurn($turn_id) {
		if ($this->game_rec['turn_list'][$turn_id]['attack_rune'] === null
				|| $this->game_rec['turn_list'][$turn_id]['defend_rune'] === null) {
			return false;
		}

		$attack_score = $this->game_rec['turn_list'][$turn_id]['attack_mana_count'];
		$defend_score = $this->game_rec['turn_list'][$turn_id]['defend_mana_count'];
		if ($this->isRuneGreater($this->game_rec['turn_list'][$turn_id]['attack_rune'], $this->game_rec['turn_list'][$turn_id]['defend_rune'])) {
			$attack_score++;
		} else if ($this->isRuneGreater($this->game_rec['turn_list'][$turn_id]['defend_rune'], $this->game_rec['turn_list'][$turn_id]['attack_rune'])) {
			$defend_score++;
		}

		$this->game_rec['turn_list'][$turn_id]['attack_score'] = $attack_score;
		$this->game_rec['turn_list'][$turn_id]['defend_score'] = $defend_score;

		if ($attack_score > $defend_score) {
			$attack_success_ind = 1;
			$this->game_rec['state']['space_owner_map'][$this->game_rec['turn_list'][$turn_id]['defend_space_id']] = $this->game_rec['turn_list'][$turn_id]['attack_player_id'];
		} else {
			$attack_success_ind = 0;
		}

		$this->game_rec['turn_list'][$turn_id]['attack_success_ind'] = $attack_success_ind;


		$this->addEventAttackResult($turn_id,
			$attack_success_ind,
			$this->game_rec['turn_list'][$turn_id]['attack_rune'],
			$this->game_rec['turn_list'][$turn_id]['defend_rune']
		);


		$player0_score = count($this->getPlayerLargestContiguousSpaceList(0));
		$player1_score = count($this->getPlayerLargestContiguousSpaceList(1));

		$this->addEventScores(array(
			0 => $player0_score,
			1 => $player1_score
		));


		// if no more runes or all spaces captured, game over, else start next turn
		if ($this->getPlayerSpaceCount(0) == 0) {
			$this->endGame(1);
		} else if ($this->getPlayerSpaceCount(1) == 0) {
			$this->endGame(0);
		} else if (!$this->playersHaveRunes()) {
			if ($player0_score > $player1_score) {
				$this->endGame(0);
			} else if ($player1_score > $player0_score) {
				$this->endGame(1);
			} else {
				// tie
				$this->endGame(null);
			}

		} else {
			$this->turnNew(($this->game_rec['turn_list'][$turn_id]['attack_player_id'] + 1) % 2);
		}

		return true;
	}


	//////////////////////////////////////////////////////////


	protected function turnNew($player_id) {
		$turn_id = count($this->game_rec['turn_list']);

		$this->game_rec['turn_list'][$turn_id] = array(
			'turn_id' => $turn_id,
			'attack_player_id' => $player_id,

			'defend_player_id' => null,
			'attack_space_id' => null,
			'defend_space_id' => null,

			'attack_rune' => null,
			'attack_mana_count' => null,

			'defend_rune' => null,
			'defend_mana_count' => null,

			'attack_success_ind' => null
		);

		$this->addEventNewTurn($turn_id, $player_id);
	}

	protected function addEventNewTurn($turn_id, $player_id) {
		$this->addEvent(array(
			'kind' => 'new_turn',
			'player_id' => $player_id
		));
	}

	protected function addEventAttack($turn_id, $attack_space_id, $defend_space_id, $defend_player_id) {
		$this->addEvent(array(
			'kind' => 'attack',
			'turn_id' => $turn_id,
			'attack_space_id' => $attack_space_id,
			'defend_space_id' => $defend_space_id,
			'defend_player_id' => $defend_player_id
		));
	}

	protected function addEventAttackResult($turn_id, $attack_success_ind, $attack_rune, $defend_rune) {
		$this->addEvent(array(
			'kind' => 'attack_result',
			'turn_id' => $turn_id,
			'attack_success_ind' => $attack_success_ind,
			'attack_rune' => $attack_rune,
			'defend_rune' => $defend_rune
		));
	}

	protected function addEventScores($score_table) {
		$this->addEvent(array(
			'kind' => 'current_scores',
			'player_score_table' => $score_table
		));
	}

	protected function addEventGameOver($winner_player_id, $winning_space_list = null) {
		$this->addEvent(array(
			'kind' => 'game_over',
			'winner_player_id' => $winner_player_id,
			'winning_space_list' => $winning_space_list
		));
	}

	protected function addEvent($event_rec) {
		$event_id = count($this->game_rec['event_list']);

		$event_rec['event_id'] = $event_id;

		$this->game_rec['event_list'][$event_id] = $event_rec;
	}


	//////////////////////////////////////////////////////////


	protected function endGame($winner_player_id) {
		$this->game_rec['state']['game_over_ind'] = 1;
		$this->game_rec['state']['winner_player_id'] = $winner_player_id;

		if ($winner_player_id !== null) {
			$winning_space_list = $this->getPlayerLargestContiguousSpaceList($winner_player_id);
		}

		$this->addEventGameOver($winner_player_id, $winning_space_list);
	}

	function startGame() {
		$this->ready();

		if ($this->getOpenPlayerCount() > 0) {
			$this->error(Runez::ERR_WAITING_FOR_PLAYERS, 'Waiting for players to join');
			return false;
		}

		if (!$this->areAllPlayersReady()) {
			$this->error(Runez::ERR_WAITING_FOR_PLAYERS, 'Some players not ready');
			return false;
		}

		if ($this->gameStarted()) {
			$this->error(Runez::ERR_GAME_IN_PROGRESS, 'Game has already started');
			return false;
		}

		$this->game_rec['state'] = $this->game_rec['initial_state'];
		$this->game_rec['state']['game_over_ind'] = 0;
		$this->game_rec['state']['winner_player_id'] = null;

		$this->game_rec['turn_list'] = array();
		$this->game_rec['event_list'] = array();


		$player0_score = count($this->getPlayerLargestContiguousSpaceList(0));
		$player1_score = count($this->getPlayerLargestContiguousSpaceList(1));

		$this->addEventScores(array(
			0 => $player0_score,
			1 => $player1_score
		));


		$start_player_id = mt_rand(0, 1);
		$turn_id = $this->turnNew($start_player_id);

		return true;
	}

	function registerPlayer($player_id = null, $name = null) {
		$this->ready();

		if ($this->gameStarted()) {
			$this->error(Runez::ERR_GAME_IN_PROGRESS, 'Game has already started');
			return false;
		}

		if ($player_id === null) {
			$player_id = $this->getNextOpenPlayerId();
			if ($player_id === false) {
				$this->error(Runez::ERR_MAX_PLAYERS_REGISTERED, 'Game full, no more players can join');
				return false;
			}
		} else {
			if (!array_key_exists($player_id, $this->game_rec['player_table'])) {
				$this->error(Runez::ERR_INVALID_PLAYER_ID, 'Invalid player');
				return false;
			}
		}

		if ($this->game_rec['player_table'][$player_id]['registered_ind'] == 1) {
			$this->error(Runez::ERR_PLAYER_ALREADY_REGISTERED, 'Player already registered');
			return false;
		}

		$this->game_rec['player_table'][$player_id]['registered_ind'] = 1;
		$this->game_rec['player_table'][$player_id]['player_key'] = 'p' . $player_id; //randIdent(32);

		$name = trim($name);
		if ($name != null) {
			$this->game_rec['player_table'][$player_id]['name'] = $name;
		}


		$this->initializePlayerOptions($player_id);


		return array(
			'player_id' => $player_id,
			'player_key' => $this->game_rec['player_table'][$player_id]['player_key'],
			'player_name' => $this->game_rec['player_table'][$player_id]['name']
		);
	}

	function setPlayerOptions($player_id, $options_rec) {
		$this->ready();

		if ($this->gameStarted()) {
			$this->error(Runez::ERR_GAME_IN_PROGRESS, 'Game has already started');
			return false;
		}

		if ($this->playerReady($player_id)) {
			$this->error(Runez::ERR_PLAYER_SETUP_CLOSED, 'Player options have already been set');
			return false;
		}


		$rune_counts = array_map('intval', arrayPullFields($options_rec, array('rune_sun_count', 'rune_moon_count', 'rune_earth_count')));
		$rune_total = array_sum($rune_counts);

		if ($rune_total != $this->game_rec['runez_options']['initial_rune_count']) {
			$this->error(Runez::ERR_INCORRECT_RUNE_COUNT, 'The player must start with a total of ' . $this->game_rec['runez_options']['initial_rune_count'] . ' runes');
			return false;
		}

		$this->game_rec['initial_state']['player_table'][$player_id]['rune_sun_count'] = $rune_counts['rune_sun_count'];
		$this->game_rec['initial_state']['player_table'][$player_id]['rune_moon_count'] = $rune_counts['rune_moon_count'];
		$this->game_rec['initial_state']['player_table'][$player_id]['rune_earth_count'] = $rune_counts['rune_earth_count'];

		return true;
	}

	function setPlayerReady($player_id, $ready_ind = 1) {
		$this->ready();

		if ($this->gameStarted()) {
			$this->error(Runez::ERR_GAME_IN_PROGRESS, 'Game has already started');
			return false;
		}

		$this->game_rec['player_table'][$player_id]['ready_ind'] = $ready_ind;
	}


	function getPlayerOptionsParameters() {
		$this->ready();

		return array(
			'initial_rune_count' => $this->game_rec['runez_options']['initial_rune_count']
		);
	}


	//////////////////////////////////////////////////////////


	function getCurrentBoardState() {
		$this->ready();

		return $this->game_rec['state']['space_owner_map'];
	}

	function getInitialState() {
		$this->ready();

		return $this->game_rec['initial_state']['space_owner_map'];
	}

	function getPlayerList() {
		$this->ready();

		$player_list = array();
		foreach ($this->game_rec['player_table'] as $player_rec) {
			if ($player_rec['registered_ind'] == 1) {
				$player_list[] = arrayPullFields($player_rec, array('player_id', 'name', 'color'), true);
			}
		}

		return $player_list;
	}


	//////////////////////////////////////////////////////////


	function playerValid($player_id, $player_key) {
		$this->ready();

		if (!$this->isPlayerIdValid($player_id)) {
			$this->error(Runez::ERR_INVALID_PLAYER, 'invalid player id');
			return false;
		}

		if (!$this->isPlayerRegistered($player_id)) {
			$this->error(Runez::ERR_INVALID_PLAYER, 'player not registered');
			return false;
		}

		if (!$this->isPlayerKeyValid($player_id, $player_key)) {
			$this->error(Runez::ERR_INVALID_PLAYER, 'invalid player key');
			return false;
		}

		return true;
	}


	//////////////////////////////////////////////////////////


	protected function gameStarted() {
		return ($this->game_rec['state'] != null);
	}

	protected function playerReady($player_id) {
		return ($this->game_rec['player_table'][$player_id]['ready_ind'] == 1);
	}

	protected function initializePlayerOptions($player_id) {
		$this->game_rec['initial_state']['player_table'][$player_id]['mana_count'] = $this->game_rec['runez_options']['initial_mana_count'];

		$runes = array(0, 0, 0);
		$runes_left = $this->game_rec['runez_options']['initial_rune_count'];

		while ($runes_left > 0) {
			$runes[mt_rand(0, 2)]++;
			$runes_left--;
		}

		$this->game_rec['initial_state']['player_table'][$player_id]['rune_moon_count'] = $runes[0];
		$this->game_rec['initial_state']['player_table'][$player_id]['rune_sun_count'] = $runes[1];
		$this->game_rec['initial_state']['player_table'][$player_id]['rune_earth_count'] = $runes[2];
	}

	protected function isPlayerIdValid($player_id) {
		return (array_key_exists($player_id, $this->game_rec['player_table'])
			&& $this->game_rec['player_table'][$player_id]['player_id'] == $player_id
		);
	}

	protected function isPlayerRegistered($player_id) {
		return ($this->game_rec['player_table'][$player_id]['registered_ind'] == 1);
	}

	protected function isPlayerKeyValid($player_id, $player_key) {
		return ($this->game_rec['player_table'][$player_id]['player_key'] == $player_key);
	}

	protected function isPlayerReady($player_id) {
		return ($this->game_rec['player_table'][$player_id]['ready_ind'] == 1);
	}

	protected function areAllPlayersReady() {
		foreach ($this->game_rec['player_table'] as $player_rec) {
			if ($player_rec['registered_ind'] == 1 && $player_rec['ready_ind'] != 1) {
				return false;
			}
		}

		return true;
	}

	protected function getOpenPlayerCount() {
		$count = 0;

		foreach ($this->game_rec['player_table'] as $player_rec) {
			if ($player_rec['registered_ind'] != 1) {
				$count++;
			}
		}

		return $count;
	}

	protected function getNextOpenPlayerId() {
		$player_id = false;

		$num_players = count($this->game_rec['player_table']);

		$i = 0;
		while ($i < $num_players && $player_id === false) {
			if ($this->game_rec['player_table'][$i]['registered_ind'] != 1) {
				$player_id = $i;
			}

			$i++;
		}

		return $player_id;
	}

	protected function playersHaveRunes() {
		foreach ($this->game_rec['state']['player_table'] as $player_rec) {
			if ($player_rec['rune_sun_count'] > 0
				|| $player_rec['rune_moon_count'] > 0
				|| $player_rec['rune_earth_count'] > 0) {

				return true;
			}
		}

		return false;
	}



	//////////////////////////////////////////////////////////


	protected function getPlayerSpaceCount($player_id) {
		$count = 0;

		foreach ($this->game_rec['state']['space_owner_map'] as $owner_player_id) {
			if ($owner_player_id == $player_id) {
				$count++;
			}
		}

		return $count;
	}

	protected function getPlayerLargestContiguousSpaceList($player_id) {
		$owner_space_table = array();
		foreach ($this->game_rec['state']['space_owner_map'] as $space_id => $owner_player_id) {
			if ($player_id == $owner_player_id) {
				$owner_space_table[$space_id] = $space_id;
			}
		}

		if ($owner_space_table == null) {
			return false;
		}

		$contiguous_lists = array();

		while (count($owner_space_table) > 0) {
			$start_space_id = array_pop($owner_space_table);

			$search_space_id_list = array();
			$contiguous_space_id_list = array();

			array_push($search_space_id_list, $start_space_id);
			array_push($contiguous_space_id_list, $start_space_id);

			while ($search_space_id_list != null) {
				// this space has already been determined to be a player's space and is in the contiguous list
				// - this iteration is to find its neighbors owned by the player
				$search_space_id = array_pop($search_space_id_list);

				foreach ($this->game_rec['board']['space_table'][$search_space_id]['adjacent_space_id_list'] as $space_id) {
					// check if space has already been checked
					if (array_key_exists($space_id, $owner_space_table)) {
						unset($owner_space_table[$space_id]);

						// if space is owned by player, add it to search list
						if ($this->game_rec['state']['space_owner_map'][$space_id] == $player_id) {
							array_push($search_space_id_list, $space_id);
							array_push($contiguous_space_id_list, $space_id);
						}
					}
				}
			}

			$contiguous_lists[] = $contiguous_space_id_list;
		}


		$largest_id = null;
		$largest = 0;

		foreach ($contiguous_lists as $list_id => $contiguous_list) {
			$count = count($contiguous_list);
			if ($count > $largest) {
				$largest_id = $list_id;
				$largest = $count;
			}
		}

		return $contiguous_lists[$largest_id];
	}

	protected function isSpaceIdValid($space_id) {
		return (array_key_exists($space_id, $this->game_rec['board']['space_table']));
	}

	protected function isPlayerSpace($player_id, $space_id) {
		return ($this->game_rec['state']['space_owner_map'][$space_id] == $player_id);
	}

	protected function setPlayerSpace($player_id, $space_id) {
		$this->game_rec['state']['space_owner_map'][$space_id] = $player_id;
	}

	protected function getSpacePlayer($space_id) {
		return ($this->game_rec['state']['space_owner_map'][$space_id]);
	}

	protected function areSpacesAdjacent($space1_id, $space2_id) {
		return (array_search($space1_id, $this->game_rec['board']['space_table'][$space2_id]['adjacent_space_id_list']) !== false
				&& array_search($space2_id, $this->game_rec['board']['space_table'][$space1_id]['adjacent_space_id_list']) !== false);
	}


	//////////////////////////////////////////////////////////


	protected function getNewGameRecord() {
		return array(
			'state' => null,

			'turn_list' => array(),
			'event_list' => array(),

			'player_table' => array(
				0 => array(
					'player_id' => 0,
					'player_key' => null,
					'registered_ind' => 0,
					'ready_ind' => 0,
					'name' => 'The Blue Player',
					'color' => 'blue'
				),
				1 => array(
					'player_id' => 1,
					'player_key' => null,
					'registered_ind' => 0,
					'ready_ind' => 0,
					'name' => 'The Red Player',
					'color' => 'red'
				)
			),

			'board' => array(
				'space_table' => array(
					0 => array(
						'adjacent_space_id_list' => array(1, 4)
					),
					1 => array(
						'adjacent_space_id_list' => array(0, 2, 5)
					),
					2 => array(
						'adjacent_space_id_list' => array(1, 3, 6)
					),
					3 => array(
						'adjacent_space_id_list' => array(2, 7)
					),

					4 => array(
						'adjacent_space_id_list' => array(0, 5, 8)
					),
					5 => array(
						'adjacent_space_id_list' => array(1, 4, 6, 9)
					),
					6 => array(
						'adjacent_space_id_list' => array(2, 5, 7, 10)
					),
					7 => array(
						'adjacent_space_id_list' => array(3, 6, 11)
					),

					8 => array(
						'adjacent_space_id_list' => array(4, 9, 12)
					),
					9 => array(
						'adjacent_space_id_list' => array(5, 8, 10, 13)
					),
					10 => array(
						'adjacent_space_id_list' => array(6, 9, 11, 14)
					),
					11 => array(
						'adjacent_space_id_list' => array(7, 10, 15)
					),

					12 => array(
						'adjacent_space_id_list' => array(8, 13)
					),
					13 => array(
						'adjacent_space_id_list' => array(9, 12, 14)
					),
					14 => array(
						'adjacent_space_id_list' => array(10, 13, 15)
					),
					15 => array(
						'adjacent_space_id_list' => array(11, 14)
					)
				)
			),

			'runez_options' => array(
				'initial_mana_count' => 6,
				'initial_rune_count' => 4
			),

			'initial_state' => array(
				'game_over_ind' => 0,
				'winner_player_id' => null,

				'space_owner_map' => array(
					0 => 0,
					1 => 0,
					2 => 0,
					3 => 0,

					4 => 0,
					5 => 0,
					6 => 0,
					7 => 0,

					8 => 1,
					9 => 1,
					10 => 1,
					11 => 1,

					12 => 1,
					13 => 1,
					14 => 1,
					15 => 1
				),

				'player_table' => array(
					0 => array(
						'mana_count' => null,
						'rune_sun_count' => null,
						'rune_moon_count' => null,
						'rune_earth_count' => null
					),
					1 => array(
						'mana_count' => null,
						'rune_sun_count' => null,
						'rune_moon_count' => null,
						'rune_earth_count' => null
					)
				)

			)
		);
	}

}


