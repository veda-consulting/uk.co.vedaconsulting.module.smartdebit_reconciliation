<?php

class CRM_SmartdebitReconciliation_Utils {

  CONST c_ContributionStatus = "contribution_status";

  static function _get_membership_type($membershipTypeID) {
    $membershipTypeDetails = CRM_Member_BAO_MembershipType::getMembershipTypeDetails($membershipTypeID);
    if (!isset($membershipTypeDetails['name'])) {
      return "Not Found";
    }
    else {
      return $membershipTypeDetails['name'];
    }
  }

  static function _get_membership_status($membershipStatusID) {
    $membershipStatusDetails = CRM_Member_BAO_MembershipStatus::getMembershipStatus($membershipStatusID);
    if (!isset($membershipStatusDetails['label'])) {
      return "Not Found";
    }
    else {
      return $membershipStatusDetails['label'];
    }
  }

  static function get_membership($contactID, $membershipID = NULL) {
    $membershipDetails = CRM_Member_BAO_Membership::getAllContactMembership($contactID);

    $membershipOptions = null;

    foreach ($membershipDetails as $key => $detail) {
      if(!empty( $detail['start_date'] )) {
        $start_date = date( 'Y-m-d', strtotime($detail['start_date']));
      } else {
        $start_date = "Null";
      }
      if (!empty($detail['end_date'])) {
        $end_date = date( 'Y-m-d', strtotime($detail['end_date']));
      } else {
        $end_date = "Null";
      }
      $type = self::_get_membership_type($detail['membership_type_id']);
      $status = self::_get_membership_status($detail['status_id']);

      /*$aMembershipOption[] = array(
                               'id'         => $mem_id,
                               'start_date' => $start_date,
                               'end_date'   => $end_date,
                               'type'       => $type,
                               'status'     => $status
                             ); */
      $membershipOptions[$key]['id'] = $key;
      $membershipOptions[$key]['start_date'] = $start_date;
      $membershipOptions[$key]['end_date'] = $end_date;
      $membershipOptions[$key]['type'] = $type;
      $membershipOptions[$key]['status'] = $status;
      $membershipOptions[$key] = $type.'/'.$status.'/'.$start_date.'/'.$end_date;
    }
    $membershipOptions['donation'] = 'Donation';
    if ($membershipID && isset($membershipOptions[$membershipID])) {
      return $membershipOptions[$membershipID];
    }
    else {
      return $membershipOptions;
    }
  }

  static function _get_ContributionId_By_ContributionRecurId($cRecurID) {
    $contributionParams = array(
      'version'               => 3,
      'sequential'            => 1,
      'contribution_recur_id' => $cRecurID
    );
    $aContributionRecur = civicrm_api('Contribution', 'get', $contributionParams);
    return $aContributionRecur['values'][0];
  }

  static function get_Recurring_Record($contactID) {
    $cRecur = null;
    $aContributionRecur = CRM_Contribute_BAO_ContributionRecur::getRecurContributions($contactID);
    foreach ( $aContributionRecur as $ContributionRecur){
      $sql = " SELECT name FROM civicrm_payment_processor WHERE id = %1 ";
      $param = array( 1 => array($ContributionRecur['payment_processor_id'], 'Integer') );
      $dao = CRM_Core_DAO::singleValueQuery($sql, $param);

      /*$acontribution = self::_get_ContributionId_By_ContributionRecurId( $ContributionRecur['id'] );
      $cRecur[] = array(
          'contribution_id'       => $acontribution['id'],
          'contribution_recur_id' => $ContributionRecur['id'],
          'status'                => $ContributionRecur['contribution_status'],
          'amount'                => $ContributionRecur['amount'],
          'payment_processor'     => $dao,
      );*/

      $cRecur[$ContributionRecur['id']] = $dao.'/'.$ContributionRecur['contribution_status'].'/'.$ContributionRecur['amount'];
    }
    $cRecur['new_recur'] = 'Create New Recurring';
    return $cRecur;
  }

  static function _get_contribution_recur($cRecurID) {
    $cRecurParams = array(
      'version'     => 3,
      'sequential'  => 1,
      'id'          => $cRecurID
    );
    $aContributionRecur = civicrm_api('ContributionRecur', 'get', $cRecurParams);
    if(!$aContributionRecur['is_error']){
      $cRecur = $aContributionRecur['values'][0];
    }

    // get contribution status label
    $cStatusGroupName = self::c_ContributionStatus;
    $cStatusOpGroup = self::_get_optionGroup($cStatusGroupName);
    $cStatusOpValue = self::_get_optionValue($cStatusOpGroup['id'], $cRecur['contribution_status_id']);

    //get payment processor name
    if(!empty($cRecur['payment_processor_id'])){
      $sql   = "SELECT name 
                FROM civicrm_payment_processor 
                WHERE id = %1
                ";
      $param = array( 1 => array( $cRecur['payment_processor_id'], 'Integer') );
      $dao   = CRM_Core_DAO::singleValueQuery($sql, $param);
    }

    $contributionRecur = array();
    if(!empty($cRecur)){
      $contributionRecur = array(
        'id'                => $cRecur['id'],
        'status'            => $cStatusOpValue['label'],
        'amount'            => $cRecur['amount'],
        'payment_processor' => $dao
      );
    }
    return $contributionRecur;
  }

  static function _get_optionValue($opGroupID, $value) {
    $optionValue = array(
      'version'         => 3,
      'sequential'      => 1,
      'value'           => $value,
      'option_group_id' => $opGroupID
    );
    $aOptionValue = civicrm_api('OptionValue', 'get', $optionValue);
    if(!$aOptionValue['is_error']){
      return $aOptionValue['values'][0];
    }else{
      return $aOptionValue['error_message'];
    }
  }

  static function _get_optionGroup($groupName) {
    $optionGroup = array(
      'version'     => 3,
      'sequential'  => 1,
      'name'        => $groupName
    );
    $aOptionGroup = civicrm_api('OptionGroup', 'get', $optionGroup);
    if(!$aOptionGroup['is_error']){
      return $aOptionGroup['values'][0];
    } else {
      return $aOptionGroup['error_message'];
    }
  }

  static function _get_contact_details($cid) {
    $Params = array(
      'version'     => 3,
      'sequential'  => 1,
      'id'          => $cid
    );
    $aContact = civicrm_api('Contact', 'get', $Params);
    if( !$aContact['is_error'] ){
      return $aContact['values'][0];
    }else{
      return $aContact['error_message'];
    }
  }

  static function _get_address($cid) {
    $Params = array(
      'version'     => 3,
      'sequential'  => 1,
      'contact_id'  => $cid
    );
    $aAddress = civicrm_api('Address', 'get', $Params);
    if(!$aAddress['is_error']) {
      if($aAddress['count'] != 0){
        return $aAddress['values'][0];
      }
      else {
        return;
      }
    }
    else {
      return $aAddress['error_message'];
    }
  }
}
