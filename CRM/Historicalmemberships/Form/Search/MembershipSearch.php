<?php

/**
 * A custom contact search.
 */
class CRM_Historicalmemberships_Form_Search_MembershipSearch extends CRM_Contact_Form_Search_Custom_Base implements CRM_Contact_Form_Search_Interface {

  /**
   * Class constructor.
   */
  public function __construct(&$formValues) {
    parent::__construct($formValues);
  }

  /**
   * Prepare a set of search fields.
   *
   * @param CRM_Core_Form $form
   *   modifiable.
   *
   * @return void
   */
  public function buildForm(&$form) {
    CRM_Utils_System::setTitle(\CRM_Historicalmemberships_ExtensionUtil::ts('Year-End Status Report'));
    $current_year = date('Y');
    $report_years = [];
    for ($i = 2021; $i < $current_year; $i++) {
      $report_years[$i] = $i;
    }
    $report_years[$current_year] = $current_year;
    // $form->add('datepicker', 'active_on', E::ts('Active on'), [], TRUE, ['time' => FALSE]);
    $form->add('select', 'report_year', ts('Report Year'), $report_years, FALSE,
    ['multiple' => 0, 'class' => 'srm-select2', 'placeholder' => ts('- select -')]
    );
    $membershipTypes = CRM_Member_PseudoConstant::membershipType();
    $form->add('select', 'membership_type_id', ts('Membership Type'), $membershipTypes, FALSE,
      ['multiple' => 0, 'class' => 'crm-select2', 'placeholder' => ts('- select -')]
    );
    $membershipStatuses = CRM_Member_PseudoConstant::membershipStatus(NULL, NULL, 'name', FALSE, TRUE);
    unset($membershipStatuses[8]);
    $form->add('select', 'membership_status_id', ts('Membership Status'), $membershipStatuses, FALSE,
      ['multiple' => 1, 'class' => 'crm-select2', 'placeholder' => ts('- select -')]
    );
    // $form->addYesNo('member_is_primary', ts('Primary Member?'), TRUE);
    $form->assign('elements', ['report_year', 'membership_type_id', 'membership_status_id']);
  }

  /**
   * Get a list of summary data points.
   *
   * @return mixed; NULL or array with keys:
   *   - summary: string
   *   - total: numeric
   */
  public function summary() {
    return NULL;
  }

  /**
   * Get a list of displayable columns.
   *
   * @return array, keys are printable column headers and values are SQL column names
   */
  public function &columns() {
    // Return by reference.
    $columns = [
      \CRM_Historicalmemberships_ExtensionUtil::ts('Contact Id') => 'contact_id',
      \CRM_Historicalmemberships_ExtensionUtil::ts('First Name') => 'first_name',
      \CRM_Historicalmemberships_ExtensionUtil::ts('Last Name') => 'last_name',
      \CRM_Historicalmemberships_ExtensionUtil::ts('Email') => 'primary_email',
      \CRM_Historicalmemberships_ExtensionUtil::ts('Title') => 'title',
      \CRM_Historicalmemberships_ExtensionUtil::ts('Credentials') => 'professional_credentials',
      \CRM_Historicalmemberships_ExtensionUtil::ts('City') => 'city',
      \CRM_Historicalmemberships_ExtensionUtil::ts('State') => 'state',
      \CRM_Historicalmemberships_ExtensionUtil::ts('Country') => 'country',
      \CRM_Historicalmemberships_ExtensionUtil::ts('Membership Type') => 'membership_type',
      \CRM_Historicalmemberships_ExtensionUtil::ts('Membership Status') => 'membership_status',
      \CRM_Historicalmemberships_ExtensionUtil::ts('Membership ID') => 'membership_id',
      \CRM_Historicalmemberships_ExtensionUtil::ts('Gender') => 'gender',
    ];
    return $columns;
  }

  /**
   * Construct a full SQL query which returns one page worth of results.
   *
   * @param int $offset
   * @param int $rowcount
   * @param null $sort
   * @param bool $includeContactIDs
   * @param bool $justIDs
   *
   * @return string, sql
   */
  public function all($offset = 0, $rowcount = 0, $sort = NULL, $includeContactIDs = FALSE, $justIDs = FALSE) {
    $sql = $this->sql($this->select(), $offset, $rowcount, $sort, $includeContactIDs, NULL);
    // error_log( 'MembershipSearch::sql: ' . print_r( $sql, true ) );.
    return $sql;
  }

  /**
   * Construct a SQL SELECT clause.
   *
   * @return string
   *   sql fragment with SELECT arguments
   */
  public function select() {
    return " DISTINCT
      contact_a.id as contact_id  ,
	  mt.name as membership_type,
	  ms.label as membership_status,
      contact_a.first_name as first_name,
	  contact_a.last_name as last_name,
	  ce.email as primary_email,
	  contact_a.job_title as title,
      m.id as membership_id,
	  UPPER(REPLACE(TRIM(REPLACE(`additional_information`.`professional_credentials_63`,'" . \CRM_Core_DAO::VALUE_SEPARATOR . "', ' ')),' ',', ')) AS professional_credentials,
	  `address_primary`.`city` AS city,
	  sp.name AS 'state',
	  country.name AS country,
	  gender.label AS gender
    ";
  }

  /**
   * Construct a SQL FROM clause.
   *
   * @return string
   *   sql fragment with FROM and JOIN clauses
   */
  public function from() {
    $params = [];
    $clause = [];
    $log_filter = '';
    $where = '';

    $membership_type = $this->_formValues['membership_type_id'];
    $clause[] = "membership_type_id = " . $membership_type;

    // $activeOn = date('Y-m-d', strtotime($this->_formValues['active_on']));
    // $params[1] = [$activeOn, 'String'];
    if (!empty($this->_formValues['membership_status_id'])) {
      if (is_array($this->_formValues['membership_status_id'])) {
        $clause[] = "status_id IN (" . implode(',', $this->_formValues['membership_status_id']) . ")";
      }
      else {
        $clause[] = "status_id IN (" . $this->_formValues['membership_status_id'] . ")";
      }
    }

    // If (in_array($membership_type, [1, 3])) {
    // If ($activeOn) {
    //  $log_filter = "Where modified_date <= %1 AND modified_date > DATE_SUB(%1, INTERVAL 1 YEAR )";
    //  $log_filter = CRM_Core_DAO::composeQuery($log_filter, $params, TRUE);
    // }.
    if (!empty($clause)) {
      $where .= implode(' AND ', $clause);
    }

    if (!empty($this->_formValues['report_year'])) {
      $report_year = intval($this->_formValues['report_year']);
      $log_filter = "Where modified_date <= %1";
      // $log_filter = "Where modified_date <= %1 AND end_date >= %2";
      $params[1] = [date('Y-m-d', mktime(0, 0, 0, 10, 31, $report_year)), 'String'];
      // $params[2] = [date('Y-m-d', mktime(0, 0, 0, 12, 31, $report_year)), 'String'];
      $log_filter = CRM_Core_DAO::composeQuery($log_filter, $params, TRUE);
    }

    // $fromWhere = CRM_Core_DAO::composeQuery($where, $params, TRUE);
    if (!empty($where)) {
      $fromWhere = ' AND ' . $where;
    }

    $from = "FROM civicrm_contact as contact_a
INNER JOIN civicrm_membership m ON (m.contact_id = contact_a.id)
left join (
  Select *
  From civicrm_membership_log
  Where id in (
    Select max(id)
    from civicrm_membership_log
    %s
    group by membership_id
  )
  %s
)
as ml on ml.membership_id = m.id
INNER JOIN civicrm_membership_status as ms on ms.id = ml.status_id
INNER JOIN civicrm_membership_type as mt on mt.id = ml.membership_type_id
LEFT JOIN civicrm_email as ce on ce.contact_id = contact_a.id
LEFT JOIN `civicrm_address` as `address_primary` ON `contact_a`.`id` =  `address_primary`.`contact_id` AND `address_primary`.`is_primary` = 1
LEFT JOIN `civicrm_value_additional_in_9` as `additional_information` ON `contact_a`.`id` =  `additional_information`.`entity_id`
LEFT JOIN `civicrm_state_province` as `sp` ON sp.id = `address_primary`.`state_province_id`
LEFT JOIN `civicrm_country` as `country` ON country.id = `address_primary`.`country_id`
LEFT JOIN `civicrm_option_value` as `gender` ON gender.value = contact_a.gender_id and gender.option_group_id = (Select id from `civicrm_option_group` Where `name` = 'gender')";

    // error_log( 'MemberSearch::from: ' . sprintf( $from, $log_filter, $fromWhere) );.
    return sprintf($from, $log_filter, $fromWhere);
    // }
    // else /*if ( 7 === $membership_type ) */{
    // Junior search.
    // if (!empty($this->_formValues['report_year'])) {
    //   $report_year = intval($this->_formValues['report_year']);
    //   $log_filter = "Where modified_date <= %1";
    //   $params[1] = [date('Y-m-d', mktime(0, 0, 0, 10, 31, $report_year)), 'String'];
    //   $log_filter = CRM_Core_DAO::composeQuery($log_filter, $params, TRUE);
    // }
    // If (!empty($clause)) {
    //   $where .= implode(' AND ', $clause);
    // }.
    // // $fromWhere = CRM_Core_DAO::composeQuery($where, $params, TRUE);
    // if (!empty($where)) {
    //   $fromWhere = ' Where ' . $where;
    // }
    // $from = "FROM civicrm_contact as contact_a
    // INNER JOIN civicrm_membership m ON (m.contact_id = contact_a.id)
    // left join (
    //   Select cml.* From civicrm_membership_log as cml inner join (
    //     Select id, membership_id, modified_date
    //     from (
    //       Select id, membership_type_id, status_id, modified_date, membership_id
    //       from civicrm_membership_log as a
    //       Where id in (
    //         Select max(id)
    //         from civicrm_membership_log
    //         %s
    //         group by membership_id
    //       )
    //     ) as b
    //     %s
    //   )
    //   as fml on cml.id = fml.id
    // )
    // as ml on ml.membership_id = m.id
    // INNER JOIN civicrm_membership_status as ms on ms.id = ml.status_id
    // INNER JOIN civicrm_membership_type as mt on mt.id = ml.membership_type_id
    // LEFT JOIN civicrm_email as ce on ce.contact_id = contact_a.id
    // LEFT JOIN `civicrm_address` as `address_primary` ON `contact_a`.`id` =  `address_primary`.`contact_id` AND `address_primary`.`is_primary` = 1
    // LEFT JOIN `civicrm_value_additional_in_9` as `additional_information` ON `contact_a`.`id` =  `additional_information`.`entity_id`
    // LEFT JOIN `civicrm_state_province` as `sp` ON sp.id = `address_primary`.`state_province_id`
    // LEFT JOIN `civicrm_country` as `country` ON country.id = `address_primary`.`country_id`";
    // error_log( 'MemberSearch::from: ' . sprintf( $from, $log_filter, $fromWhere) );.
    // return sprintf($from, $log_filter, $fromWhere);
    // }
    // Else {
    //  //All others that don't have status rules
    //  if (!empty($clause)) {
    //    $where .= implode(' AND ', $clause);
    //  }
    //  if ( !empty( $this->_formValues['report_year'] ) ) {
    //    $report_year = intval( $this->_formValues['report_year'] );
    //    $log_filter = "Where modified_date <= %1";
    //    $params[1] = [ date('Y-m-d', mktime( 0, 0, 0, 12, 31, $report_year ) ), 'String'];
    //    $log_filter = CRM_Core_DAO::composeQuery($log_filter, $params, TRUE);
    //  }
    //  // $fromWhere = CRM_Core_DAO::composeQuery($where, $params, TRUE);
    //  if ( !empty( $where ) ) {
    //    $fromWhere = ' Where ' . $where;
    //  }
    //  $from = "FROM civicrm_contact as contact_a
    //  INNER JOIN civicrm_membership m ON (m.contact_id = contact_a.id)
    //  left join (
    //        Select cml.* From civicrm_membership_log as cml inner join ( Select id, membership_id, modified_date
    //      from (
    //              Select id, membership_type_id, status_id, modified_date, membership_id
    //        from civicrm_membership_log as a
    //        Where id in ( Select max(id)
    //        from civicrm_membership_log
    //        %s
    //        group by membership_id )
    //            ) as b
    //      %s )
    //        as fml on cml.id = fml.id )
    //      as ml on ml.membership_id = m.id
    //    INNER JOIN civicrm_membership_status as ms on ms.id = ml.status_id
    //    INNER JOIN civicrm_membership_type as mt on mt.id = ml.membership_type_id
    //    LEFT JOIN civicrm_email as ce on ce.contact_id = contact_a.id
    //    LEFT JOIN `civicrm_address` as `address_primary` ON `contact_a`.`id` =  `address_primary`.`contact_id` AND `address_primary`.`is_primary` = 1
    //    LEFT JOIN `civicrm_value_additional_in_9` as `additional_information` ON `contact_a`.`id` =  `additional_information`.`entity_id`
    //    LEFT JOIN `civicrm_state_province` as `sp` ON sp.id = `address_primary`.`state_province_id`
    //    LEFT JOIN `civicrm_country` as `country` ON country.id = `address_primary`.`country_id`";
    //  // error_log( 'MemberSearch::from: ' . sprintf( $from, $log_filter, $fromWhere) );
    //  return sprintf( $from, $log_filter, $fromWhere);
    // }.
  }

  /**
   * Construct a SQL WHERE clause.
   *
   * @param bool $includeContactIDs
   *
   * @return string
   *   sql fragment with conditional expressions
   */
  public function where($includeContactIDs = FALSE) {
    $params = [];
    $where = '';
    $clause[] = "contact_a.is_deleted != 1";
    $clause[] = 'ce.is_primary = 1';
    $clause[] = "ce.email not like '%@graydigitalgroup.com'";
    $clause[] = "ce.email not like '%@graygraphics.com'";
    $clause[] = "ce.email not like '%@crusonweb.com'";
    $clause[] = "ce.email not like '%@higherlogic.com'";
    $clause[] = "ce.email not like '%@nonprofitcms.org'";

    // If (!CRM_Utils_System::isNull($this->_formValues['member_is_primary'])) {
    //   if (!empty($this->_formValues['member_is_primary'])) {
    //     $clause[] = "m.owner_membership_id IS NULL";
    //   }
    //   else {
    //     $clause[] = "m.owner_membership_id IS NOT NULL";
    //   }
    // }.
    if (!empty($clause)) {
      $where .= implode(' AND ', $clause);
    }
    return $this->whereClause($where, $params);
  }

  /**
   * Determine the Smarty template for the search screen.
   *
   * @return string, template path (findable through Smarty template path)
   */
  public function templateFile() {
    return 'CRM/Contact/Form/Search/Custom.tpl';
  }

  /**
   * Modify the content of each row.
   *
   * @param array $row modifiable SQL result row
   *
   * @return void
   */
  // Function alterRow(&$row) {
  //   $row['sort_name'] .= ' ( altered )';
  // }.

  /**
   * Validate form input.
   *
   * @param array $fields
   * @param array $files
   * @param CRM_Core_Form $self
   *
   * @return array
   *   Input errors from the form.
   */
  public function formRule($fields, $files, $self) {
    if (empty($fields['report_year'])) {
      return ['report_year' => 'Please select a year'];
    }
    if (empty($fields['membership_type_id'])) {
      return ['membership_type_id' => 'Membership Type cannont be empty.'];
    }

    if (!empty($fields['membership_type_id'])) {
      $membership = $fields['membership_type_id'];
      $statuses = $fields['membership_status_id'];
      if (!empty($statuses)) {
        $status_ids = [
          1 => 'New',
          2 => 'Current',
          3 => 'Grace',
          4 => 'Expired',
          5 => 'Pending',
          6 => 'Cancelled',
          7 => 'Deceased',
          9 => 'In Arrears',
          10 => 'Junior Pending',
          11 => 'Junior New',
          12 => 'Junior Current',
          13 => 'Junior Grace',
          14 => 'Junior Dropped',
          15 => 'Archived',
          16 => 'Dropped',
          17 => 'Medical Student Current',
          18 => 'Junior Archived',
        ];
        $status_rules_allowed = [
        // Active.
          1 => [1, 2, 3, 4, 5, 6, 7, 9, 15, 16],
        // Site-Admin.
          2 => [1, 2, 6, 7],
        // Affiliate.
          3 => [1, 2, 3, 4, 5, 6, 7, 9, 15, 16],
        // Central Office.
          4 => [1, 2, 6, 7],
        // Honorary.
          5 => [1, 2, 6, 7],
        // Emeritus.
          6 => [1, 2, 6, 7],
        // Junior.
          7 => [1, 2, 3, 4, 5, 6, 7, 10, 11, 12, 13, 14, 15, 16, 18],
        // Medical Student.
          8 => [1, 2, 6, 7, 15, 16, 17],
        // Education Membership.
          9 => [2, 6, 7],
        // PECN.
          11 => [1, 2, 3, 4, 5, 6, 7],
        // Central Office - Web Admin.
          12 => [1, 2, 6, 7],
        ];
        $allowed_statuses = $status_rules_allowed[$membership];
        $not_allowed_statuses = array_diff($statuses, $allowed_statuses);
        if (count($not_allowed_statuses)) {
          $bad_statuses = [];
          foreach ($not_allowed_statuses as $status_id) {
            $bad_statuses[] = $status_ids[$status_id];
          }
          return ['membership_status_id' => 'These Statuses are not allowed for this membership type: ' . implode(', ', $bad_statuses)];
        }
      }
    }

    return [];
  }

}
