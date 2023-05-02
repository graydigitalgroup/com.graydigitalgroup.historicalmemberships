<?php
use CRM_Historicalmemberships_ExtensionUtil as E;

/**
 * A custom contact search
 */
class CRM_Historicalmemberships_Form_Search_MembershipSearch extends CRM_Contact_Form_Search_Custom_Base implements CRM_Contact_Form_Search_Interface {

  /**
   * Class constructor.
   */
  function __construct(&$formValues) {
    parent::__construct($formValues);
  }

  /**
   * Prepare a set of search fields
   *
   * @param CRM_Core_Form $form modifiable
   * @return void
   */
  function buildForm(&$form) {
    CRM_Utils_System::setTitle(E::ts('Find Members'));
    $form->add('datepicker', 'active_on', E::ts('Active on'), [], TRUE, ['time' => FALSE]);
    $membershipTypes = CRM_Member_PseudoConstant::membershipType();
    $form->add('select', 'membership_type_id', ts('Membership Type'), $membershipTypes, FALSE,
      ['multiple' => 1, 'class' => 'crm-select2', 'placeholder' => ts('- select -')]
    );
	$membershipStatuses = CRM_Member_PseudoConstant::membershipStatus();
    $form->add('select', 'membership_status_id', ts('Membership Status'), $membershipStatuses, FALSE,
      ['multiple' => 1, 'class' => 'crm-select2', 'placeholder' => ts('- select -')]
    );
    // $form->addYesNo('member_is_primary', ts('Primary Member?'), TRUE);
    $form->assign('elements', array('active_on', 'membership_type_id', 'membership_status_id'));
  }

  /**
   * Get a list of summary data points
   *
   * @return mixed; NULL or array with keys:
   *  - summary: string
   *  - total: numeric
   */
  function summary() {
    return NULL;
  }

  /**
   * Get a list of displayable columns
   *
   * @return array, keys are printable column headers and values are SQL column names
   */
  function &columns() {
    // return by reference
    $columns = array(
      E::ts('Contact Id') => 'contact_id',
      E::ts('Name') => 'sort_name',
      E::ts('Email') => 'primary_email',
	  E::ts('Title') => 'title',
	  E::ts('Credentials') => 'professional_credentials',
	  E::ts('City') => 'city',
	  E::ts('State') => 'state',
	  E::ts('Country') => 'country',
	  E::ts('Membership Type') => 'membership_type',
	  E::ts('Membership Status') => 'membership_status',
      E::ts('Membership ID') => 'membership_id'
    );
    return $columns;
  }

  /**
   * Construct a full SQL query which returns one page worth of results
   *
   * @param int $offset
   * @param int $rowcount
   * @param null $sort
   * @param bool $includeContactIDs
   * @param bool $justIDs
   * @return string, sql
   */
  function all($offset = 0, $rowcount = 0, $sort = NULL, $includeContactIDs = FALSE, $justIDs = FALSE) {
	$sql = $this->sql($this->select(), $offset, $rowcount, $sort, $includeContactIDs, NULL);
	// error_log( 'MembershipSearch::sql: ' . print_r( $sql, true ) );
    return $sql;
  }

  /**
   * Construct a SQL SELECT clause
   *
   * @return string
   *  sql fragment with SELECT arguments
   */
  function select() {
    return " DISTINCT
      contact_a.id as contact_id  ,
	  mt.name as membership_type,
	  ms.label as membership_status,
      contact_a.sort_name    as sort_name,
	  ce.email as primary_email,
	  contact_a.job_title as title,
      m.id as membership_id,
	  UPPER(REPLACE(TRIM(REPLACE(`additional_information`.`professional_credentials_63`,'" . \CRM_Core_DAO::VALUE_SEPARATOR . "', ' ')),' ',', ')) AS professional_credentials,
	  `address_primary`.`city` AS city,
	  sp.name AS 'state',
	  country.name AS country
    ";
  }

  /**
   * Construct a SQL FROM clause
   *
   * @return string
   *  sql fragment with FROM and JOIN clauses
   */
  function from() {
	$params = [];
	$where = '';

	if (!empty($this->_formValues['membership_type_id'])) {
		$clause[] = "membership_type_id IN (" . implode(',', $this->_formValues['membership_type_id']) . ")";
	}
	$activeOn = date('Y-m-d', strtotime($this->_formValues['active_on']));
	$params[1] = [$activeOn, 'String'];
	if (!empty($this->_formValues['membership_status_id'])) {
		$clause[] = "status_id IN (" . implode(',', $this->_formValues['membership_status_id']) . ")";
	}
	if ($activeOn) {
		$clause[] = "( modified_date <= %1 AND DATE_SUB(%1, INTERVAL 1 YEAR ) )"; // AND (end_date >= %1 or end_date IS NULL)";
	}

	if (!empty($clause)) {
		$where .= implode(' AND ', $clause);
	}

	$fromWhere = CRM_Core_DAO::composeQuery($where, $params, TRUE);
	if ( !empty( $fromWhere ) ) {
		$fromWhere = ' Where ' . $fromWhere;
	}

	$from = "FROM civicrm_contact as contact_a
		INNER JOIN civicrm_membership m ON (m.contact_id = contact_a.id)
		left join (Select cml.* From civicrm_membership_log as cml inner join ( Select max(id) as id, membership_id from ( Select id, membership_id from civicrm_membership_log %s order by membership_id ) as a group by membership_id ) as fml on cml.id = fml.id ) as ml on ml.membership_id = m.id
		INNER JOIN civicrm_membership_status as ms on ms.id = ml.status_id
		INNER JOIN civicrm_membership_type as mt on mt.id = ml.membership_type_id
		LEFT JOIN civicrm_email as ce on ce.contact_id = contact_a.id
		LEFT JOIN `civicrm_address` as `address_primary` ON `contact_a`.`id` =  `address_primary`.`contact_id` AND `address_primary`.`is_primary` = 1
		LEFT JOIN `civicrm_value_additional_in_9` as `additional_information` ON `contact_a`.`id` =  `additional_information`.`entity_id`
		LEFT JOIN `civicrm_state_province` `sp` ON sp.id = `address_primary`.`state_province_id`
		LEFT JOIN `civicrm_country` as `country` ON country.id = `address_primary`.`country_id`";

	return sprintf( $from, $fromWhere);
  }

  /**
   * Construct a SQL WHERE clause
   *
   * @param bool $includeContactIDs
   * @return string
   *  sql fragment with conditional expressions
   */
  function where($includeContactIDs = FALSE) {
    $params = [];
    $where = '';
    $clause[] = "contact_a.is_deleted != 1";
	$clause[] = 'ce.is_primary = 1';
	$clause[] = "ce.email not like '%@graydigitalgroup.com'";
	$clause[] = "ce.email not like '%@crusonweb.com'";
	$clause[] = "ce.email not like '%@higherlogic.com'";

    // if (!CRM_Utils_System::isNull($this->_formValues['member_is_primary'])) {
    //   if (!empty($this->_formValues['member_is_primary'])) {
    //     $clause[] = "m.owner_membership_id IS NULL";
    //   }
    //   else {
    //     $clause[] = "m.owner_membership_id IS NOT NULL";
    //   }
    // }

    if (!empty($clause)) {
      $where .= implode(' AND ', $clause);
    }
    return $this->whereClause($where, $params);
  }

  /**
   * Determine the Smarty template for the search screen
   *
   * @return string, template path (findable through Smarty template path)
   */
  function templateFile() {
    return 'CRM/Contact/Form/Search/Custom.tpl';
  }

  /**
   * Modify the content of each row
   *
   * @param array $row modifiable SQL result row
   * @return void
   */
  // function alterRow(&$row) {
  //   $row['sort_name'] .= ' ( altered )';
  // }
}
