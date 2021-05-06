<?php

/**
 * Portal Record Model.
 *
 * @package Service
 *
 * @copyright YetiForce Sp. z o.o
 * @license   YetiForce Public License 3.0 (licenses/LicenseEN.txt or yetiforce.com)
 * @author    Radosław Skrzypczak <r.skrzypczak@yetiforce.com>
 */

/**
 * Settings_WebserviceUsers_Portal_Service class.
 */
class Settings_WebserviceUsers_Portal_Service extends Settings_WebserviceUsers_RestApi_Service
{
	/**
	 * Table name.
	 *
	 * @var string
	 */
	public $baseTable = 'w_#__portal_user';

	/**
	 * {@inheritdoc}
	 */
	public $editFields = [
		'server_id' => 'FL_SERVER', 'status' => 'FL_STATUS', 'user_name' => 'FL_LOGIN', 'password_t' => 'FL_PASSWORD', 'type' => 'FL_TYPE', 'language' => 'FL_LANGUAGE', 'crmid' => 'FL_RECORD_NAME', 'user_id' => 'FL_USER', 'istorage' => 'FL_STORAGE'
	];

	/**
	 * {@inheritdoc}
	 */
	public function init(array $data)
	{
		$data['password_t'] = App\Encryption::getInstance()->decrypt($data['password_t']);
		$this->setData($data);
		return $this;
	}

	/**
	 * Function determines fields available in edition view.
	 *
	 * @param mixed $name
	 *
	 * @return string[]
	 */
	public function getFieldInstanceByName($name)
	{
		$moduleName = $this->getModule()->getName(true);
		$fieldsLabel = $this->getEditFields();
		$params = ['uitype' => 1, 'column' => $name, 'name' => $name, 'label' => $fieldsLabel[$name], 'displaytype' => 1, 'typeofdata' => 'V~M', 'presence' => 0, 'isEditableReadOnly' => false];
		switch ($name) {
			case 'crmid':
				$params['uitype'] = 10;
				$params['referenceList'] = ['Contacts'];
				break;
			case 'istorage':
				$params['uitype'] = 10;
				$params['referenceList'] = ['IStorages'];
				$params['typeofdata'] = 'V~O';
				break;
			case 'status':
				$params['uitype'] = 16;
				$params['picklistValues'] = [1 => \App\Language::translate('PLL_ACTIVE', $moduleName), 0 => \App\Language::translate('PLL_INACTIVE', $moduleName)];
				break;
			case 'server_id':
				$servers = Settings_WebserviceApps_Module_Model::getActiveServers($this->getModule()->typeApi);
				$params['uitype'] = 16;
				foreach ($servers as $key => $value) {
					$params['picklistValues'][$key] = $value['name'];
				}
				break;
			case 'type':
				$params['uitype'] = 16;
				$params['picklistValues'] = [];
				foreach ($this->getTypeValues() as $key => $value) {
					$params['picklistValues'][$key] = \App\Language::translate($value, $moduleName);
				}
				break;
			case 'language':
				$params['typeofdata'] = 'V~O';
				$params['uitype'] = 32;
				$params['picklistValues'] = \App\Language::getAll();
				break;
			case 'user_id':
				$params['uitype'] = 16;
				$params['picklistValues'] = \App\Fields\Owner::getInstance($moduleName)->getAccessibleUsers('', 'owner');
				break;
			case 'password_t':
				$params['typeofdata'] = 'P~M';
				break;
			default:
				break;
		}
		return Settings_Vtiger_Field_Model::init($moduleName, $params);
	}

	/**
	 * Sets data from request.
	 *
	 * @param App\Request $request
	 */
	public function setDataFromRequest(App\Request $request)
	{
		foreach (array_keys($this->getEditFields()) as $field) {
			if ($request->has($field)) {
				switch ($field) {
					case 'server_id':
					case 'status':
					case 'type':
					case 'user_id':
					case 'istorage':
						$value = $request->getInteger($field);
						break;
					case 'crmid':
						$value = $request->isEmpty('crmid') ? '' : $request->getInteger('crmid');
						break;
					case 'user_name':
					case 'language':
						$value = $request->getByType($field, 'Text');
						break;
					case 'password_t':
						$value = $request->getRaw($field, null);
						break;
					default:
					throw new \App\Exceptions\Security("ERR_ILLEGAL_FIELD_VALUE||{$field}", 406);
						break;
				}
				$this->set($field, $this->getValueToSave($field, $value));
			}
		}
	}

	/**
	 * Function formats data for saving.
	 *
	 * @param string $key
	 * @param mixed  $value
	 *
	 * @return int|string
	 */
	public function getValueToSave($key, $value)
	{
		switch ($key) {
			case 'server_id':
			case 'status':
			case 'type':
			case 'crmid':
			case 'user_id':
				$value = (int) $value;
				break;
			case 'password_t':
				$value = App\Encryption::getInstance()->encrypt($value);
				break;
			default:
				break;
		}
		return $value;
	}

	/**
	 * Function to get the list view actions for the record.
	 *
	 * @return Vtiger_Link_Model[] - Associate array of Vtiger_Link_Model instances
	 */
	public function getRecordLinks()
	{
		$links = [];
		$recordLinks = [
			[
				'linktype' => 'LISTVIEWRECORD',
				'linklabel' => 'FL_PASSWORD',
				'linkicon' => 'fas fa-copy',
				'linkclass' => 'btn btn-sm btn-primary clipboard',
				'linkdata' => ['copy-attribute' => 'clipboard-text', 'clipboard-text' => \App\Purifier::encodeHtml(App\Encryption::getInstance()->decrypt($this->get('password_t')))]
			],
			[
				'linktype' => 'LISTVIEWRECORD',
				'linklabel' => 'LBL_EDIT_RECORD',
				'linkurl' => $this->getModule()->getEditViewUrl() . '&record=' . $this->getId(),
				'linkicon' => 'yfi yfi-full-editing-view',
				'linkclass' => 'btn btn-sm btn-primary',
				'modalView' => true,
			],
			[
				'linktype' => 'LISTVIEWRECORD',
				'linklabel' => 'LBL_DELETE_RECORD',
				'linkurl' => 'javascript:Settings_WebserviceUsers_List_Js.deleteById(' . $this->getId() . ');',
				'linkicon' => 'fas fa-trash-alt',
				'linkclass' => 'btn btn-sm btn-danger',
			],
		];
		foreach ($recordLinks as $recordLink) {
			$links[] = Vtiger_Link_Model::getInstanceFromValues($recordLink);
		}
		return $links;
	}
}
