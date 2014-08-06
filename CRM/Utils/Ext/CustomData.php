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

    $optionValue = civicrm_api('OptionValue', 'GetValue', array(
      'version' => 3,
      'name' => $optionName,
      'option_group_id' => $group_id,
      'return' => 'value'
    ));

    if (is_string($optionValue)) { // already exists, do nothing.
      return $optionValue;
    }

    $params = array(
      'version' => 3,
      'name' => $optionName,
      'label' => $optionLabel,
      'option_group_id' => $group_id,
    );

    if(!is_null($newValue)) {
      $params['value'] = $newValue;
    }

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
}

