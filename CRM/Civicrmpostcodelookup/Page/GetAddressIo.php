<?php

class CRM_Civicrmpostcodelookup_Page_GetAddressIo extends CRM_Civicrmpostcodelookup_Page_Postcode {

	/*
	 * Function to get address list based on a Post code
	 */
	public static function search() {
		$postcode = self::getPostcode();
		$number = CRM_Utils_Request::retrieve('number', 'String', $this, false);

		$apiUrl = self::getAddressIoApiUrl($postcode, $number);

		// get address result from getAddress.io
		$addressData = self::addressAPIResult($apiUrl);

		$addresslist = array();
		if ($addressData['is_error']) {
			$addresslist[0]['value'] = '';
			$addresslist[0]['label'] = 'Error in fetching address';
		} else {
			$addresslist = self::getAddressList($addressData, $postcode);
		}

		// Check CiviCRM version & return result as appropriate
		$civiVersion = CRM_Civicrmpostcodelookup_Utils::getCiviVersion();
		if ($civiVersion < 4.5) {
			foreach ($addresslist as $key => $val) {
        echo "{$val['label']}|{$val['id']}\n";
      }
		} else {
			echo json_encode($addresslist);
		}
		exit;
	}

	/*
	 * Function to get address details based on the selected address
	 */
	public static function getaddress() {
		$selectedId = CRM_Utils_Request::retrieve('id', 'String', $this, true);

		// get postcode & address key from selectedId
		$selectedResult = explode('_', $selectedId);
		$postcode = $selectedResult[0];
		$addressKey = $selectedResult[1];

		$apiUrl = self::getAddressIoApiUrl($postcode);

		// get address result from getAddress.io
		$addressData = self::addressAPIResult($apiUrl);

		$addresslist = array();
		if ($addressData['is_error']) {
			$address = array();
		} else {
			$addressItems = $addressData['addresses'];

			// selected result from the addressItems
			$addressItem = $addressItems[$addressKey];

			$address = self::formatAddressLines($selectedId, $addressItem);
			// Fix me : postcode not returned in the API result, hence using the one from the selected ID
			$address['postcode'] = $postcode;
		}

		$response = array(
			'address' => $address
		);

		echo json_encode($response);
		exit;
	}

	/*
	 * Function to get the API URL
	 */
	private static function getAddressIoApiUrl($postcode = NULL, $number = NULL) {
		#################
		#API settings
		#################
		$settingsStr = CRM_Core_BAO_Setting::getItem('CiviCRM Postcode Lookup', 'api_details');
  	$settingsArray = unserialize($settingsStr);

  	$servertarget = $settingsArray['server'];

  	// https://api.getAddress.io/find/{postcode}/{house}
  	$servertarget = $servertarget . "/find";

  	// search by postcode
  	if ($postcode && !empty($postcode)) {
  		$servertarget = $servertarget . "/" . $postcode;

  		// search by house number
	  	if ($number && !empty($number)) {
	  		$servertarget = $servertarget . "/" . $number;
	  	}
  	}

  	$apiKey = $settingsArray['api_key'];

  	$querystring = "api-key=$apiKey";
		return $servertarget ."?" . $querystring;
	}

	/*
	*Function to get Address result from getAddress.io
	*/
	private static function addressAPIResult($apiUrl) {
		$addressData = array();

		if (empty($apiUrl)) {
			$addressData['is_error'] = 1;
			CRM_Core_Error::debug_var('apiURL empty in get addressAPIResult ', ' ');
			return $addressData;
		}

		##Get the Address Data##
		$curl = curl_init();
		curl_setopt_array($curl, array(
	    CURLOPT_RETURNTRANSFER => 1,
	    CURLOPT_URL => $apiUrl,
	    CURLOPT_USERAGENT => 'CiviCRM'
		));

		$result = curl_exec($curl);
		curl_close($curl);
		## End of Get the Address Data##

		if (curl_errno($curl)) {
			// Log & return error
			$addressData['is_error'] = 1;
			CRM_Core_Error::debug_var('Curl error in GetAddressio ', $curl);
		}else {
			$resultObject = json_decode($result);
			$addressData = (array)$resultObject;
			$addressData['is_error'] = 0;
		}
		return $addressData;
	}

	private static function getAddressList($addressData, $postcode) {
		$addressList = array();
		$addressRow = array();

		// return, if adddressData/postcode is empty
		if (empty($addressData) || empty($postcode)) {
			$addressRow["id"] = '';
		  $addressRow["value"] = '';
		  $addressRow["label"] = 'Postcode Not Found';
		  array_push($addressList, $addressRow);
			return $addressList;
		}
		$AddressListItem = $addressData['addresses'];
		foreach ($AddressListItem as $key => $addressItem) {

			// FIX me : There is no address id found in th API, hence assigning combination of postcode & arrayresultID as rowId inorder to get the selected address later
			$addressId = $postcode . '_' . $key;

			$addressLineArray = self::formatAddressLines($addressId, $addressItem, TRUE);
			$addressLineArray['postcode'] = $postcode;

			$addressRow["id"] = $addressId;
		  $addressRow["value"] = $postcode;
		  $addressRow["label"] = @implode(', ', $addressLineArray);;
		  array_push($addressList, $addressRow);
		}

		if (empty($addressList)) {
			$addressRow["id"] = '';
		  $addressRow["value"] = '';
		  $addressRow["label"] = 'Postcode Not Found';
		  array_push($addressList, $addressRow);
		}

		return $addressList;
	}

	private static function formatAddressLines($addressId, $addressItem, $forList = FALSE) {

		if (empty($addressItem)) {
			return;
		}

		$addressLines = explode(', ', $addressItem);

		if ($forList == FALSE) {
			$address = array('id' => $addressId);
		}
		if (!empty($addressLines[0])) {
			$address["street_address"] = $addressLines[0];
		}
		if (!empty($addressLines[1])) {
			$address["supplemental_address_1"] = $addressLines[1];
		}
		if (!empty($addressLines[2])) {
			$address["supplemental_address_2"] = $addressLines[2];
		}
		if (!empty($addressLines[5])) {
			$address["town"] = $addressLines[5];
		}

		// Get state/county
		$states = CRM_Core_PseudoConstant::stateProvince();

		$address["state_province_id"] = '';
		if (!empty($addressLines[6])) {

			$stateId = array_search($addressLines[6], $states);

			if ($stateId) {
				$address["state_province_id"] = $stateId;
			}
		}

		return $address;
	}

}