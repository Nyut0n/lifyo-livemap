<?php

# Dependency: class MySQL

class Guild {
	
	const TICKET_RETRIES = 6;
	const TICKET_RETRY_INTERVAL = 1;
	
	# Get claim tier by diameter
	public static function get_claim_tier( $diameter ) {
		if( $diameter <= 30  ) return 1;
		if( $diameter <= 50  ) return 2;
		if( $diameter <= 100 ) return 3;
		return 4;
	}
	
	# Convert NULL permissions from database to 0/1 string
	protected static function convert_null_permissions( $subjects ) {
		foreach( $subjects AS &$s ) {
			$s['CanEnter'] = (bool)$s['CanEnter'] ? '1' : '0';
			$s['CanBuild'] = (bool)$s['CanBuild'] ? '1' : '0';
			$s['CanClaim'] = (bool)$s['CanClaim'] ? '1' : '0';
			$s['CanUse'] = (bool)$s['CanUse'] ? '1' : '0';
			$s['CanDestroy'] = (bool)$s['CanDestroy'] ? '1' : '0';
		}
		return $subjects;
	}

	# ================================================================================================================== #
	
	private $db;
	public $id, $name, $tag, $charter, $type, $diameter, $center_x, $center_y, $center_geoid, $leader_steam_id, $leader_char_id, $claim_id, $land_id, $founded;
	
	private $producer_char_id;

	public function __construct( MySQL $db ) {
		$this->db = $db;
	}
	
	# ================================================================================================================== #
	
	# Load details from database
	public function load( $guild_id ) {
		
		$this->id = intval($guild_id);
		
		$rs = $this->db->query( "SELECT g.Name, g.GuildTag, g.GuildCharter, g.GuildTypeID, g.CreateTimestamp, c.ID AS LeaderCharID, a.SteamID AS LeaderSteamID, gl.ID AS LandID, claims.ID AS ClaimID, gl.Radius, gl.CenterGeoID
									FROM `guilds` g
									JOIN `character` c ON g.ID = c.GuildID AND c.GuildRoleID = 1
									JOIN `account` a ON c.AccountID = a.ID
									LEFT JOIN `guild_lands` gl ON g.ID = gl.GuildID AND gl.LandType < 4
									LEFT JOIN `claims` on gl.ID = claims.GuildLandID
									WHERE g.ID = '$this->id'
									ORDER BY g.Name LIMIT 1", FALSE );
		if( empty($rs) ) return FALSE;
		
		$this->name = $rs['Name'];
		$this->tag  = $rs['GuildTag'];
		$this->type = intval($rs['GuildTypeID']);
		$this->leader_steam_id = $rs['LeaderSteamID'];
		$this->leader_char_id = intval($rs['LeaderCharID']);
		$this->claim_id = intval($rs['ClaimID']);
		$this->land_id = intval($rs['LandID']);
		$this->diameter = intval($rs['Radius']);
		$this->center_geoid = intval($rs['CenterGeoID']);
		$this->founded = $rs['CreateTimestamp'];
		$this->details = json_decode($rs['GuildCharter'], TRUE);
		
		return TRUE;
		
	}
	
	# Get all members
	public function get_members() {
		
		return $this->db->query( "SELECT c.ID, c.Name, c.LastName, c.GuildRoleID, a.SteamID, ASCII( SUBSTRING(c.appearance,1) ) AS Gender
									FROM `character` c, `account` a 
									WHERE c.AccountID = a.ID AND c.GuildID = '$this->id'
									ORDER BY c.GuildRoleID, c.Name" );
		
	}

	# Get guild standings
	public function get_standings() {
		
		return $this->db->query( "SELECT g.ID, g.Name, g.GuildTypeID, gl.Radius, gs1.StandingTypeID AS TheirStanding, gs2.StandingTypeID AS OurStanding, gl.CenterGeoID, 
										 CONCAT(c.Name, ' ', c.LastName) AS LeaderName, ASCII( SUBSTRING(c.appearance,1) ) AS LeaderGender
									FROM `guilds` g
									JOIN `character` c ON c.GuildID = g.ID AND c.GuildRoleID = 1
									JOIN `guild_lands` gl ON g.ID = gl.GuildID AND gl.LandType < 4
									LEFT JOIN `guild_standings` gs1 ON g.ID = gs1.GuildID2 AND gs1.GuildID1 = $this->id
									LEFT JOIN `guild_standings` gs2 ON g.ID = gs2.GuildID1 AND gs2.GuildID2 = $this->id
									WHERE g.ID <> $this->id
									ORDER BY g.Name ASC" );
									
	}

	# Get rank permissions
	public function get_rank_permissions() {
		
		$r = $this->db->query( "SELECT gr.ID AS GuildRoleID, cr.CanEnter, cr.CanBuild, cr.CanClaim, cr.CanUse, cr.CanDestroy
									FROM guild_roles gr
									LEFT JOIN claim_subjects cs
										INNER JOIN claim_rules cr
										ON cr.ClaimSubjectID = cs.ID AND cr.ClaimID = '$this->claim_id'
									ON cs.GuildRoleID = gr.ID
									ORDER BY gr.ID" );
		return self::convert_null_permissions($r);
		
	}
	
	# Get standing permissions
	public function get_standing_permissions() {
					
		$r = $this->db->query( "SELECT gst.ID AS StandingTypeID, cr.CanEnter, cr.CanBuild, cr.CanClaim, cr.CanUse, cr.CanDestroy
									FROM guild_standing_types gst
									LEFT JOIN claim_subjects cs
										INNER JOIN claim_rules cr
										ON cr.ClaimSubjectID = cs.ID AND cr.ClaimID = '$this->claim_id'
									ON cs.StandingTypeID = gst.ID
									ORDER BY gst.ID" );
		return self::convert_null_permissions($r);
		
	}
	
	# Get foreign guilds individual permissions
	public function get_guild_permissions() {
					
		$r = $this->db->query( "SELECT cs.GuildID, g.Name, cr.CanEnter, cr.CanBuild, cr.CanClaim, cr.CanUse, cr.CanDestroy
									FROM guilds g, claim_rules cr, claim_subjects cs
									WHERE cr.ClaimID = '$this->claim_id' AND cr.ClaimSubjectID = cs.ID AND cs.GuildID = g.ID" );
		return self::convert_null_permissions($r);
		
	}
	
	# Get foreign characters individual permissions
	public function get_char_permissions() {
					
		$r = $this->db->query( "SELECT cs.CharID, c.Name, c.LastName, cr.CanEnter, cr.CanBuild, cr.CanClaim, cr.CanUse, cr.CanDestroy
									FROM `character` c, `claim_rules` cr, `claim_subjects` cs
									WHERE cr.ClaimID = '$this->claim_id' AND cr.ClaimSubjectID = cs.ID AND cs.CharID = c.ID" );
		return self::convert_null_permissions($r);
		
	}
	
	# Check administrative privileges by SteamID and optionally set producer_char_id
	public function is_admin( $steam_id, $setproducer = FALSE ) {
		$rs = $this->db->query( "SELECT c.ID, c.GuildRoleID FROM `character` c, `account` a WHERE c.AccountID = a.ID AND c.GuildID = '$this->id' AND a.SteamID = '$steam_id' ORDER BY GuildRoleID ASC LIMIT 1", FALSE );
		if( ! $rs || intval($rs['GuildRoleID']) > 2 ) return FALSE;
		if( $setproducer ) $this->producer_char_id = intval($rs['ID']);
		return TRUE;
	}
	
	# Check for membership by steamID
	public function is_member( $steam_id ) {
		$rs = $this->db->query( "SELECT c.ID FROM `character` c, `account` a WHERE c.AccountID = a.ID AND c.GuildID = '$this->id' AND a.SteamID = '$steam_id' LIMIT 1", FALSE );
		if( ! $rs ) return FALSE;
		return TRUE;
	}
	
	# ================================================================================================================== #
	
	public function get_detail( $key ) {
	
		if( $this->details === NULL ) return FALSE;
		if( ! isSet($this->details[$key]) ) return FALSE;
		return $this->details[$key];

	}
	
	public function update_charter( $charter, $public ) {
		
		$this->update_charter_object( "GuildCharterPublic", $public );
		$this->update_charter_object( "GuildCharter", $charter );
		return TRUE;
		
	}
	
	private function update_charter_object( $key, $value ) {
		
		$rs = $this->db->query( "SELECT GuildCharter FROM guilds WHERE ID = '$this->id'", FALSE );
		$data = json_decode($rs['GuildCharter'], TRUE);
		if( $data === NULL ) $data = array();
		$data[$key] = $value;
		$json = $this->db->esc(json_encode($data));
		$this->db->query( "UPDATE guilds SET GuildCharter = '$json' WHERE ID = '$this->id'" );
		return TRUE;
		
	}
	
	# ================================================================================================================== #
	
	public function kick_member( $char_id ) {
		$rs = $this->db->query( "SELECT fb_kickFromGuild('$this->producer_char_id', '$char_id', '$this->id') AS TicketID", FALSE );
		return $this->process_ticket($rs['TicketID']);
	}
	
	public function change_standing( $guild_id, $standing_id, $async = FALSE ) {
		$rs = $this->db->query( "SELECT fb_setGuildStanding('$this->producer_char_id', '$this->id', '$guild_id', '$standing_id') AS TicketID", FALSE );
		if( $async ) {
			return $rs['TicketID'];
		} else {
			return $this->process_ticket($rs['TicketID']);
		}
	}
	
	public function change_rank( $char_id, $rank_id ) {
		$rs = $this->db->query( "SELECT fb_setCharGuildRole('$this->producer_char_id', '$char_id', '$this->id', '$rank_id') AS TicketID", FALSE );
		return $this->process_ticket($rs['TicketID']);
	}
	
	public function change_permission( $type, $id, $enter, $build, $claim, $use, $destroy ) {
		switch($type) {
			case 'standing':	$function = 'fb_setClaimRuleGuildStanding'; break;
			case 'rank':		$function = 'fb_setClaimRuleGuildRole'; 	break;
			case 'guild':		$function = 'fb_setClaimRuleGuild'; 		break;
			case 'char':		$function = 'fb_setClaimRuleChar';			break;
			default: return false;
		}
		$rs = $this->db->query( "SELECT $function('$this->producer_char_id', '$this->claim_id', '$id', $enter, $build, $claim, $use, $destroy) AS TicketID", FALSE );
		return $this->process_ticket($rs['TicketID']);
	}
	
	public function delete_permission( $type, $id ) {
		switch($type) {
			case 'guild':	$function = 'fb_removeClaimRuleGuild'; 	break;
			case 'char':	$function = 'fb_removeClaimRuleChar';	break;
			default: return false;
		}
		$rs = $this->db->query( "SELECT $function('$this->producer_char_id', '$this->claim_id', '$id') AS TicketID", FALSE );
		return $this->process_ticket($rs['TicketID']);
	}
	
	# Process guild action though ticket system
	#  result codes:
	#   1 success
	#   0 failed
	#  -1 timed out
	public function process_ticket( $ticket_id ) {
		
		// Reject invalid tickets
		if( $ticket_id === "0" ) return 0;

		// Check for result a few times
		$retry_count = 0;
		do {
			sleep(self::TICKET_RETRY_INTERVAL);
			$rs = $this->db->query( "SELECT ProcessedStatus FROM guild_actions_processed WHERE TicketID = '$ticket_id'", FALSE );
		} while( empty($rs) && ++$retry_count < self::TICKET_RETRIES );
		
		// Failed to respond. Server offline. Timeout
		if( empty($rs) ) {
			$this->db->query( "DELETE FROM guild_actions_queue WHERE TicketID = '$ticket_id'" );
			return -1;
		} 
		
		// Ticket result
		if( $rs['ProcessedStatus'] === 'failed' ) return 0;
		return 1;
		
	}
	

}

?>