<?php
/*
Plugin Name: BP Groups Multisite - access limit
Plugin URI: http://www.dhrusya.com/products/bp-groups-multisite/
Description: Remember which site a BuddyPress group was created on and limit access to that group to that site only.
Version: .1
Author:  Venugopal Chaladi
Author URI: http://dhrusya.com
Requires at least: 3.8
Tested up to:5 .1
*/

// Add meta fields upon group creation
function bpsg_group_meta_save ( $group_id ) 
{
	$blog = get_blog_details( get_current_blog_id(), true );
	
	$fields = array(
		'blog_id' => $blog->blog_id,
		'blog_path' => $blog->path,
		'blog_name' => $blog->blogname
	);
	
	foreach ( $fields as $field => $value ) {
		groups_update_groupmeta( $group_id, $field, $value );
	}
}
add_action( 'groups_created_group', 'bpsg_group_meta_save' );

//get groups by meta value
function bpsg_get_groups_by_meta ( $field, $meta_key, $meta_value ) 
{
	global $wpdb;
	
	if ( is_string( $meta_value) ) $meta_value = "'" . $meta_value . "'";
	
	$sql = $wpdb->prepare( "SELECT $field from {$wpdb->base_prefix}bp_groups_groupmeta WHERE meta_key='$meta_key' AND meta_value=$meta_value", OBJECT );
	$res = $wpdb->get_results( $sql );
	
	return $res;
}

// Build a list of groups with the matching blog_id value
function bpsg_get_groups_by_blogid ( $blog_id = 1 ) 
{
	$list = bpsg_get_groups_by_meta( 'group_id', 'blog_id', $blog_id );
	
	if ( count( $list ) ) {
	$res = "";
		foreach ( $list as $item ) {
		$res .= $item->group_id . ',';
	}
		return substr( $res, 0, -1);
	} else {
		return FALSE;
	}
}

/*
	Filter groups to show only groups for the current site.
	
	$groups here is an array:
	[groups] = groups array
	[total] = number of groups in the array
	
	DEV NOTE: May need to update this to work on the groups property of the 
	BP_Groups_Template class directly since sometimes (when viewing invites) the 
	groups are not generated through groups_get_groups().	
*/
function bpsg_groups_get_groups($groups)
{	
	//get current site/blog
	$current_site = get_blog_details( get_current_blog_id(), true );
		
	//loop through groups and check site
	$newgroups = array();
	for($i = 0; $i < count($groups['groups']); $i++)
	{
		//check site for group
		$group_site = groups_get_groupmeta($groups['groups'][$i]->id, "blog_id");
				
		if($group_site == $current_site->blog_id)
			$newgroups[] = $groups['groups'][$i];
	}
	
	//update groups
	$groups['groups'] = $newgroups;
	
	//update count
	$groups['total'] = count($groups['groups']);

	return $groups;
}
add_filter('groups_get_groups', 'bpsg_groups_get_groups');
	
/*
	Redirect users away from groups that don't belong to the current site.
*/
function bpsg_template_redirect()
{	
	//make sure BP is activated
	if(!function_exists('bp_get_current_group_id'))
		return;

	//get group id
	$group_id = bp_get_current_group_id();
		
	//is this a group page?
	if(!empty($group_id))
	{
		//check the site
		$current_site = get_blog_details( get_current_blog_id(), true );
		$group_site = groups_get_groupmeta($group_id, "blog_id");
				
		if($current_site->blog_id != $group_site)
		{
			//send them home
			wp_redirect(home_url());
			exit;
		}
	}
}
add_action('template_redirect', 'bpsg_template_redirect');

/*
	Redirect admins from editing non-site groups from the dashboard.
*/
function bpsg_admin_init_redirect()
{
	if(!empty($_REQUEST['page']) && $_REQUEST['page'] == 'bp-groups' && !empty($_REQUEST['action']) && $_REQUEST['action'] == 'edit' && !empty($_REQUEST['gid']))
	{
		//get group id
		$group_id = intval($_REQUEST['gid']);
		
		//check the site
		$current_site = get_blog_details( get_current_blog_id(), true );		
		$group_site = groups_get_groupmeta($group_id, "blog_id");
		
		if($current_site->blog_id != $group_site)
		{
			//send them home
			wp_redirect(admin_url());
			exit;
		}
	}
}
add_action('admin_init', 'bpsg_admin_init_redirect');


//users list
function bp_groups_mu_users_list( $args = '' ) {
		global $bp, $blog_id;
		if ( !bp_is_active( 'friends' ) )
			return false;
			
		$defaults = array(
			'group_id'  => false,
			'separator' => 'li'
		);
		
		$r = wp_parse_args( $args, $defaults );
		extract( $r, EXTR_SKIP );

		if ( empty( $group_id ) )
			$group_id = !empty( $bp->groups->new_group_id ) ? $bp->groups->new_group_id : $bp->groups->current_group->id;

		if ( $friends = friends_get_friends_invite_list( bp_loggedin_user_id(), $group_id ) ) {
			$invites = groups_get_invites_for_group( bp_loggedin_user_id(), $group_id );
			
			
			
			for ( $i = 0, $count = count( $friends ); $i < $count; ++$i ) {
				$checked = '';
				$userid=$friends[$i]['id'];
			
			
				$usersites=get_user_meta($userid, 'user_web', true);
				$usersites=explode(",",$usersites);
				
				if(in_array($userid, $usersites)){
		
						if ( !empty( $invites ) ) {
							if ( in_array( $friends[$i]['id'], $invites ) )
								$checked = ' checked="checked"';
						}
		
						$items[] = '<' . $separator . '><input' . $checked . ' type="checkbox" name="friends[]" id="f-' . $friends[$i]['id'] . '" value="' . esc_attr( $friends[$i]['id'] ) . '" /> ' . $friends[$i]['full_name'] . '</' . $separator . '>';
				}
			}
		}

		if ( !empty( $items ) )
			return implode( "\n", (array) $items );
		return false;
	}
