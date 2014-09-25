<?php

class CRM_Utils_Ext_CustomData {
  /**
   * lookup the AUTO_INCREMENT values for the custom_group and custom_field tables
   *
   * return array ( 'civicrm_custom_group' => <ID>, 'civicrm_custom_field' => <ID>)
   */
  public static function getCustomDataNextIDs() {
    $result = array();

    $query = "SELECT `table_name`, `AUTO_INCREMENT` FROM `information_schema`.`TABLES`
      WHERE `table_schema` = DATABASE()
      AND `table_name` IN ('civicrm_custom_group', 'civicrm_custom_field')";
    $dao = CRM_Core_DAO::executeQuery($query);
    while ($dao->fetch()) {
      $result[$dao->table_name] = (int) $dao->AUTO_INCREMENT;
    }

    return $result;
  }
  /**
   * Load Smarty and parse a tpl from the relative path given
   *
   * @param type $relativePath
   * @return boolean
   */
  public static function executeCustomDataTemplateFile($relativePath) {
      $smarty = CRM_Core_Smarty::singleton();
      $xmlCode = $smarty->fetch($relativePath);
      $xml = simplexml_load_string($xmlCode);

      $import = new CRM_Utils_Migrate_Import();
      $import->runXmlElement($xml);
      return TRUE;
  }
  /**
   * Create new Option Value with a check based on name.
   * If exists, only the label will be updated to preserve existing value.
   * Returns the activity type ID
   *
   * @param array/int $optionGroup result of api OptionGroup Get, OR int Group ID
   * @param string $optionName
   * @param string $optionLabel
   * @param string $newValue Optional
   *
   * @return int $value
   *
   * @throws API_Exception
   */
  public static function safeCreateOptionValue($optionGroup, $optionName, $optionLabel, $newValue = null) {

    if (is_numeric($optionGroup)) {
      $group_id = $optionGroup;
    } else {
      $group_id = $optionGroup['id'];
    }

    $params = array(
      'version' => 3,
      'name' => $optionName,
      'option_group_id' => $group_id,
    );

    $apiOptionValue = civicrm_api('OptionValue', 'getsingle', array_merge($params,
      array('return' => array('value', 'id'))
    ));

    if (CRM_Utils_Array::value('is_error', $apiOptionValue)) {
      // doesn't exist
      if(!is_null($newValue)) {
        $params['value'] = $newValue;
      }
    } else {
      $params['id'] = $apiOptionValue['id'];
    }

    $params['label'] = $optionLabel;

    try {
      $result = civicrm_api3('OptionValue', 'create', $params);
    } catch (CiviCRM_API3_Exception $x) {
      throw new API_Exception(
        'Exception in safeCreateOptionValue. API3_Exception: '.$x->getMessage()
        , $x->getErrorCode(), $x->getExtraParams(), $x
        );
    }

    return $result['values'][$result['id']]['value'];
  }
  /**
   * Add all fields in a Custom Group to a Profile.
   * Field label and weight will be preserved.
   * Does nothing if the CustomGroup contains no fields.
   *
   * @param int $uf_group_id the profile ID
   * @param string $custom_group_name
   *
   * @return boolean false on failure to find fields to add.
   * @throws CiviCRM_API3_Exception
   */
  public static function profileAddCustomGroupFields($uf_group_id, $custom_group_name) {

    $api_custom_group = civicrm_api3('CustomGroup', 'getsingle',
      array('name' => $custom_group_name, 'return' => array('id', 'extends'))
    );

    $custom_group_id = $api_custom_group['id'];
    $custom_group_type = $api_custom_group['extends'];

    $apiResult = civicrm_api3('CustomField', 'get',
      array( 'options' => array('limit' => 0), // no limit
      'custom_group_id' => $custom_group_id,
    ));

    if ($apiResult['count'] == 0 ) {
      return FALSE;
    }

    $params = array();
    foreach ($apiResult['values'] as $field_def) {
      $params[] = array(
        'uf_group_id' => $uf_group_id,
        'field_name' => 'custom_'.$field_def['id'],
        'field_type' => $custom_group_type,
        'label' => $field_def['label'],
        'weight' => $field_def['weight'],
      );
    }

    foreach ($params as $field) {
      civicrm_api3('UFField', 'create', $field);
    }
    return TRUE;
  }
}

