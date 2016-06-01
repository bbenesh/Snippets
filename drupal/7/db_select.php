/**
 * Helper function to retrieve a list of users, which can be narrowed down
 *  by group and also by role(s) on that group
 *
 * @param integer $gid
 *   the id of the group you'd like to search for
 * @param array $rids
 *   an array of role ids. role ids only work for roles other than "member" since
 *   the assignment of the member role is not tracked in the user_roles table.
 *   to get all users in a group, leave the array empty (array()). To get all
 *   users in a group who are _members_, feed the function an array with an
 *   empty or NULL value in it (array('') or array(NULL). You CANNOT combine
 *   an rid with an empty/null search (array('', 10)) as it will only result in
 *   users with the given rid and will not return users who are "members" as well.
 * @param string $string
 *   string to search user names
 * @return mixed
 *   returns and array filled with objects, each carrying an associative array
 *   of aggregated user/role data. Example of structure and data:
 *   Array
 *   (
 *     [0] => stdClass Object
 *       (
 *         [uid] => 1
 *         [name] => admin
 *         [mail] => admin@example.com
 *         [status] => 1
 *         [gid] => 3677
 *         [group_type] => node
 *         [group_bundle] =>
 *         [rid] =>
 *         [role_name] =>
 *         [membership_id] => 3682
 *         [membership_state] => 1
 *         [membership_field] => og_user_node
 *       )
 *   )
 */
function nhd_get_users_by_group_and_roles($gid=NULL, $rids=array(), $string='') {

  /* BUILD THE QUERY */
  /* Here is the sql we are trying to achieve:
   *
   * SELECT  u.uid,
   * u.name,
   * u.mail,
   * u.status AS user_status,
   *
   * ogm.id AS membership_id,
   * ogm.state AS membership_status,
   * ogm.field_name as membership_field,
   * ogm.gid AS gid,
   * ogm.group_type AS group_type,
   *
   * ogr.group_bundle AS bundle,
   * ogur.rid AS role_id,
   * ogr.name AS role_name
   *
   * FROM users u
   * INNER JOIN og_membership ogm ON u.uid = ogm.etid
   * LEFT JOIN og_users_roles ogur ON ogm.etid = ogur.uid AND ogm.gid = ogur.gid
   * LEFT JOIN og_role ogr ON ogur.rid = ogr.rid
   *
   * WHERE ogm.entity_type = 'user'
   * ORDER BY ogm.gid ASC;
   */
  $query = db_select('users', 'u');
  $query->innerJoin('og_membership', 'ogm', 'ogm.etid = u.uid');
  $query->leftJoin('og_users_roles', 'ogur', 'ogm.etid = ogur.uid AND ogm.gid = ogur.gid');
  $query->leftJoin('og_role', 'role', 'ogur.rid = role.rid');
  $query
    ->condition('ogm.entity_type', 'user', '=')
    ->fields('u', array('uid', 'name', 'mail', 'status'))
    ->fields('ogm', array('gid', 'group_type'))
    ->fields('role', array('group_bundle', 'rid', 'name'));
  $query->addField('ogm', 'id', 'membership_id');
  $query->addField('ogm', 'state', 'membership_state');
  $query->addField('ogm', 'field_name', 'membership_field');
  
  /* CONDITIONAL FILTERS BASED ON OUR PARAMETERS */
  // if we have a group id, filter results by that
  if($gid) {
    $query->condition('ogm.gid', $gid, '=');
  }

  // if we have any role ids, filter results by those
  if(!empty($rids)) {

    // if our array is not populated solely with empty or NULL
    // values, then lets search the db for all rids in the array
    if(count(array_filter($rids, 'strlen')) > 0) {

      // if there are empty or null values mixed in with our string values in
      // this array, let's make sure we query correctly to get NULL rids
      if(in_array('', $rids, TRUE) || in_array(NULL, $rids, TRUE)) {
        $query->condition(db_or()->condition('role.rid', $rids, 'IN')->isNull('role.rid'));

      // otherwise, let's just do a simple search for all the values in the array
      } else {
        $query->condition('role.rid', $rids, 'IN');
      }

    // if the rid array is populated with values that consist ONLY of
    // empty or NULL values then we should narrow by empty or NULL values in
    // the db (this results in getting users who have the role 'member' as
    // this appears as a NULL or empty value in the user_role table in the db.
    } else {
      $query->condition(db_or()->condition('role.rid', '', '=')->isNull('role.rid'));
    }
  }

  // if we have a search string, let's search for users like it, and limit to
  // top 10 results so as not to overwhelm user
  if($string) {
    $query->condition('u.name', db_like($string) . '%', 'LIKE')->range(0, 10);
  }

  // get ALL THE THINGS!
  return $query->execute()->fetchAll();
}

/**
 * get rid of teacher role on school groups
 */
$query = db_select('og_role')->fields('og_role', array('rid'))->condition('og_role.group_bundle', 'school', '=')->condition('og_role.name', 'teacher', '=')->execute()->fetch();

  /**
   * Renders the link.
   */
  function render_students($entity, $values) {

    //dpm($values, 'values');
    //get entry id
    $entry_nid = $values->og_membership_gid;
    //get contest id
    $contest_nid = $values->field_og_contest_group_ref[0]['raw']['target_id'];
    //get mship where gid=contest and etid=entry
    $result = db_select('og_membership')
      ->fields('og_membership')
      ->condition('etid', $entry_nid, '=')
      ->condition('gid', $contest_nid. '=')
      ->execute()
      ->fetchAll();
    //dpm($result, 'result');

    $link = 'group/node/'.$contest_nid.'/admin/people/delete-membership/'.$result[0]->id;
    return $link;

  }
}


