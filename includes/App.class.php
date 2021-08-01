<?php
/*** *** *** *** *** ***
* @package Quadodo Login Script
* @file    App.class.php
* @start   October 10th, 2007
* @author  Douglas Rennehan
* @license http://www.opensource.org/licenses/gpl-license.php
* @version 1.0.1
* @link    http://www.quadodo.net
*** *** *** *** *** ***
* This program is free software; you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with this program; if not, write to the Free Software
* Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*** *** *** *** *** ***
* Comments are always before the code they are commenting.
*** *** *** *** *** ***/
if (!defined('QUADODO_IN_SYSTEM')) {
    exit;
}

/**
 * Contains application functions which are globally usable
 */
class App {

/**
 * @var object $qls - Will contain everything else
 */
var $qls;

	/**
	 * Constructs the class
	 * @param object $qls - Reference to the rest of the program
	 * @return void
	 */
	function __construct(&$qls) {
	    $this->qls = &$qls;
		
		// Gather entitlement data
		$this->gatherEntitlementData();
		if(time() - $this->entitlementArray['lastChecked'] > ENTITLEMENT_CHECK_FREQUENCY) {
			$this->updateEntitlementData();
			$this->gatherEntitlementData();
		}
		
		// Generate environment tree object
		$this->envTreeArray = array();
		$query = $this->qls->SQL->select('*', 'app_env_tree', false, array('name', 'ASC'));
		while($row = $this->qls->SQL->fetch_assoc($query)) {
			$row['name'] = $this->convertHyphens($row['name']);
			$this->envTreeArray[$row['id']] = $row;
		}
		
		// Add full path names for each environment tree object
		foreach($this->envTreeArray as &$entry) {
			$parentID = $entry['parent'];
			$nameString = $entry['name'];
			while($parentID != '#') {
				$nameString = $this->envTreeArray[$parentID]['name'].'.'.$nameString;
				$parentID = $this->envTreeArray[$parentID]['parent'];
			}
			$entry['nameString'] = $nameString;
		}
		unset($entry);
		
		$this->floorplanObjDetails = array(
			'walljack' => array(
				'trunkable' => true,
				'populatable' => true,
				'floorplanConnectable' => true,
				'html' => '<i class="floorplanObject selectable fa fa-square-o fa-lg" style="cursor:grab;" data-type="walljack"></i>'
			),
			'wap' => array(
				'trunkable' => true,
				'populatable' => true,
				'floorplanConnectable' => false,
				'html' => '<i class="floorplanObject selectable fa fa-wifi fa-lg" style="cursor:grab;" data-type="wap"></i>'
			),
			'device' => array(
				'trunkable' => false,
				'populatable' => true,
				'floorplanConnectable' => false,
				'html' => '<i class="floorplanObject selectable fa fa-laptop fa-lg" style="cursor:grab;" data-type="device"></i>'
			),
			'camera' => array(
				'trunkable' => true,
				'populatable' => true,
				'floorplanConnectable' => false,
				'html' => '<i class="floorplanObject selectable fa fa-video-camera fa-lg" style="cursor:grab;" data-type="camera"></i>'
			)
		);
		
		$this->templateCategoryArray = array();
		$this->templateArray = array();
		$query = $this->qls->SQL->select('*', 'app_object_templates');
		while($row = $this->qls->SQL->fetch_assoc($query)) {
			
			$templateID = $row['id'];
			$templateType = $row['templateType'];
			$categoryID = $row['templateCategory_id'];
			$templateName = $row['templateName'];
			
			if(isset($this->floorplanObjDetails[$templateType])) {
				$this->floorplanObjDetails[$templateType]['templateID'] = $templateID;
			}
			
			if(!isset($this->templateCategoryArray[$categoryID])) {
				$this->templateCategoryArray[$categoryID] = array();
			}
			
			$this->templateCategoryArray[$categoryID][$templateName] = array(
				'type' => 'regular',
				'id' => $templateID
			);
			
			$this->templateArray[$templateID] = $row;
		}
		
		$this->combinedTemplateArray = array();
		$query = $this->qls->SQL->select('*', 'app_combined_templates');
		while($row = $this->qls->SQL->fetch_assoc($query)) {
			
			$templateID = $row['id'];
			$categoryID = $row['templateCategory_id'];
			$templateName = $row['templateName'];
			
			if(!isset($this->templateCategoryArray[$categoryID])) {
				$this->templateCategoryArray[$categoryID] = array();
			}
			
			$this->templateCategoryArray[$categoryID][$templateName] = array(
				'type' => 'combined',
				'id' => $templateID
			);
			
			$this->combinedTemplateArray[$templateID] = $row;
		}
		
		// Sort templates by name
		foreach($this->templateCategoryArray as &$category) {
			ksort($category);
		}
		
		// Generate object... object... <.<  >.>
		$this->objectArray = array();
		$this->objectByTemplateArray = array();
		$this->objectByCabinetArray = array();
		$query = $this->qls->SQL->select('*', 'app_object');
		while($row = $this->qls->SQL->fetch_assoc($query)) {
			$row['name'] = $this->convertHyphens($row['name']);
			$objID = $row['id'];
			$this->objectArray[$objID] = $row;
			
			$templateID = $row['template_id'];
			if(!isset($this->objectByTemplateArray[$templateID])) {
				$this->objectByTemplateArray[$templateID] = array();
			}
			array_push($this->objectByTemplateArray[$templateID], $objID);
			
			$cabinetID = $row['env_tree_id'];
			if(!isset($this->objectByCabinetArray[$cabinetID])) {
				$this->objectByCabinetArray[$cabinetID] = array();
			}
			array_push($this->objectByCabinetArray[$cabinetID], $objID);
		}
		
		//Generate insert object
		$this->insertArray = array();
		$this->insertAddressArray = array();
		//$query = $this->qls->SQL->select('*', 'app_object', array('parent_id' => array('<>', 0)));
		//while($row = $this->qls->SQL->fetch_assoc($query)) {
		foreach($this->objectArray as $entry) {
			$parentID = $entry['parent_id'];
			$parentFace = $entry['parent_face'];
			$parentDepth = $entry['parent_depth'];
			$insertSlotX = $entry['insertSlotX'];
			$insertSlotY = $entry['insertSlotY'];
			if($parentID > 0) {
				if(!isset($this->insertArray[$parentID])) {
					$this->insertArray[$parentID] = array();
				}
				array_push($this->insertArray[$parentID], $entry);
				$this->insertAddressArray[$parentID][$parentFace][$parentDepth][$insertSlotX][$insertSlotY] = $entry;
			}
		}
		
		// Add full path names for each object... this is dependant on envTreeArray, templateArray, and objectArray
		foreach($this->objectArray as &$object) {
			$nameString = $this->generateObjectName($object['id']);
			$object['nameString'] = $nameString;
		}
		
		$this->categoryArray = array();
		$query = $this->qls->SQL->select('*', 'app_object_category', false, 'name ASC');
		while($row = $this->qls->SQL->fetch_assoc($query)) {
			$this->categoryArray[$row['id']] = $row;
		}
		
		$this->portOrientationArray = array();
		$query = $this->qls->SQL->select('*', 'shared_object_portOrientation');
		while($row = $this->qls->SQL->fetch_assoc($query)) {
			$this->portOrientationArray[$row['id']] = $row;
		}
		
		$this->cablePathArray = array();
		$query = $this->qls->SQL->select('*', 'app_cable_path');
		while($row = $this->qls->SQL->fetch_assoc($query)) {
			$this->cablePathArray[$row['id']] = $row;
		}
		
		$this->cabinetAdjacencyArray = array();
		$this->cabinetAdjacencyArrayFixed = array();
		$query = $this->qls->SQL->select('*', 'app_cabinet_adj');
		while($row = $this->qls->SQL->fetch_assoc($query)) {
			$leftCabID = $row['left_cabinet_id'];
			$rightCabID = $row['right_cabinet_id'];
			
			if(!isset($this->cabinetAdjacencyArrayFixed[$leftCabID])) {
				$this->cabinetAdjacencyArrayFixed[$leftCabID] = array('left' => 0, 'right' => 0);
			}
			if(!isset($this->cabinetAdjacencyArrayFixed[$rightCabID])) {
				$this->cabinetAdjacencyArrayFixed[$rightCabID] = array('left' => 0, 'right' => 0);
			}
			$this->cabinetAdjacencyArrayFixed[$leftCabID]['right'] = $rightCabID;
			$this->cabinetAdjacencyArrayFixed[$rightCabID]['left'] = $leftCabID;
			
			$this->cabinetAdjacencyArray[$row['left_cabinet_id']] = $row;
			$this->cabinetAdjacencyArray[$row['right_cabinet_id']] = $row;
		}
		
		$this->compatibilityArray = array();
		$query = $this->qls->SQL->select('*', 'app_object_compatibility');
		while($row = $this->qls->SQL->fetch_assoc($query)) {
			$this->compatibilityArray[$row['template_id']][$row['side']][$row['depth']] = $row;
		}
		
		$this->connectorTypeArray = array();
		$this->connectorTypeValueArray = array();
		$query = $qls->SQL->select('*', 'shared_cable_connectorType');
		while ($row = $qls->SQL->fetch_assoc($query)) {
			if(strtolower($row['name']) != 'label') {
				$this->connectorTypeArray[$row['value']] = $row['name'];
				$this->connectorTypeValueArray[$row['value']] = $row;
			}
		}
		
		$this->portTypeArray = array();
		$this->portTypeValueArray = array();
		$query = $qls->SQL->select('*', 'shared_object_portType');
		while($row = $qls->SQL->fetch_assoc($query)) {
			$this->portTypeArray[$row['id']] = $row;
			$this->portTypeValueArray[$row['value']] = $row;
		}
		
		$this->mediaTypeArray = array();
		$this->mediaTypeByIDArray = array();
		$query = $qls->SQL->select('*', 'shared_mediaType', array('display' => array('=', 1)));
		while($row = $qls->SQL->fetch_assoc($query)) {
			array_push($this->mediaTypeArray, $row);
			$this->mediaTypeByIDArray[$row['value']] = $row;
		}
		
		$this->mediaTypeValueArray = array();
		$query = $qls->SQL->select('*', 'shared_mediaType');
		while($row = $qls->SQL->fetch_assoc($query)) {
			$this->mediaTypeValueArray[$row['value']] = $row;
		}
		
		$this->mediaCategoryTypeArray = array();
		$query = $qls->SQL->select('*', 'shared_mediaCategoryType');
		while($row = $qls->SQL->fetch_assoc($query)) {
			$this->mediaCategoryTypeArray[$row['value']] = $row;
		}
		
		// Generate port description object
		$this->portDescriptionArray = array();
		$this->portDescriptionAllArray = array();
		$query = $this->qls->SQL->select('*', 'app_port_description');
		while($row = $this->qls->SQL->fetch_assoc($query)) {
			
			$objID = $row['object_id'];
			$objFace = $row['object_face'];
			$objDepth = $row['object_depth'];
			$portID = $row['port_id'];
			
			$this->portDescriptionArray[$objID][$objFace][$objDepth][$portID] = $row;
			$this->portDescriptionAllArray[$row['id']] = $row;
		}
		
		$this->inventoryArray = array();
		$this->inventoryAllArray = array();
		$this->inventoryByIDArray = array();
		$query = $this->qls->SQL->select('*', 'app_inventory');
		while($row = $this->qls->SQL->fetch_assoc($query)) {
			$this->inventoryAllArray[$row['id']] = $row;
			if($row['a_object_id'] != 0) {
				if(!isset($this->inventoryArray[$row['a_object_id']][$row['a_object_face']][$row['a_object_depth']][$row['a_port_id']])) {
					$this->inventoryArray[$row['a_object_id']][$row['a_object_face']][$row['a_object_depth']][$row['a_port_id']] = array();
				}
				array_push($this->inventoryArray[$row['a_object_id']][$row['a_object_face']][$row['a_object_depth']][$row['a_port_id']], array(
					'rowID' => $row['id'],
					'id' => $row['b_object_id'],
					'face' => $row['b_object_face'],
					'depth' => $row['b_object_depth'],
					'port' => $row['b_port_id'],
					'localEndID' => $row['a_id'],
					'localAttrPrefix' => 'a',
					'remoteEndID' => $row['b_id'],
					'remoteAttrPrefix' => 'b'
				));
			}
			if($row['b_object_id'] != 0) {
				if(!isset($this->inventoryArray[$row['b_object_id']][$row['b_object_face']][$row['b_object_depth']][$row['b_port_id']])) {
					$this->inventoryArray[$row['b_object_id']][$row['b_object_face']][$row['b_object_depth']][$row['b_port_id']] = array();
				}
				array_push($this->inventoryArray[$row['b_object_id']][$row['b_object_face']][$row['b_object_depth']][$row['b_port_id']], array(
					'rowID' => $row['id'],
					'id' => $row['a_object_id'],
					'face' => $row['a_object_face'],
					'depth' => $row['a_object_depth'],
					'port' => $row['a_port_id'],
					'localEndID' => $row['b_id'],
					'localAttrPrefix' => 'b',
					'remoteEndID' => $row['a_id'],
					'remoteAttrPrefix' => 'a'
				));
			}
			if($row['a_id'] != 0) {
				$this->inventoryByIDArray[$row['a_id']] = array(
					'rowID' => $row['id'],
					'local_object_id' => $row['a_object_id'],
					'local_object_face' => $row['a_object_face'],
					'local_object_depth' => $row['a_object_depth'],
					'local_object_port' => $row['a_port_id'],
					'remote_object_id' => $row['b_object_id'],
					'remote_object_face' => $row['b_object_face'],
					'remote_object_depth' => $row['b_object_depth'],
					'remote_object_port' => $row['b_port_id'],
					'localEndID' => $row['a_id'],
					'localEndCode39' => $row['a_code39'],
					'localConnector' => $row['a_connector'],
					'localAttrPrefix' => 'a',
					'remoteEndID' => $row['b_id'],
					'remoteEndCode39' => $row['b_code39'],
					'remoteConnector' => $row['b_connector'],
					'remoteAttrPrefix' => 'b',
					'mediaType' => $row['mediaType'],
					'length' => $row['length'],
					'editable' => $row['editable']
				);
			}
			if($row['b_id'] != 0) {
				$this->inventoryByIDArray[$row['b_id']] = array(
					'rowID' => $row['id'],
					'local_object_id' => $row['b_object_id'],
					'local_object_face' => $row['b_object_face'],
					'local_object_depth' => $row['b_object_depth'],
					'local_object_port' => $row['b_port_id'],
					'remote_object_id' => $row['a_object_id'],
					'remote_object_face' => $row['a_object_face'],
					'remote_object_depth' => $row['a_object_depth'],
					'remote_object_port' => $row['a_port_id'],
					'localEndID' => $row['b_id'],
					'localEndCode39' => $row['b_code39'],
					'localConnector' => $row['b_connector'],
					'localAttrPrefix' => 'b',
					'remoteEndID' => $row['a_id'],
					'remoteEndCode39' => $row['a_code39'],
					'remoteConnector' => $row['a_connector'],
					'remoteAttrPrefix' => 'a',
					'mediaType' => $row['mediaType'],
					'length' => $row['length'],
					'editable' => $row['editable']
				);
			}
		}
		
		$this->populatedPortArray = array();
		$this->populatedPortAllArray = array();
		$query = $this->qls->SQL->select('*', 'app_populated_port');
		while ($row = $this->qls->SQL->fetch_assoc($query)){
			array_push($this->populatedPortAllArray, $row);
			$this->populatedPortArray[$row['object_id']][$row['object_face']][$row['object_depth']][$row['port_id']] = array(
				'rowID' => $row['id']
			);
		}
		
		$this->peerArrayStandard = array();
		$this->peerArray = array();
		$this->peerArrayStandardFloorplan = array();
		$this->peerArrayWalljack = array();
		$this->peerArrayWalljackEntry = array();
		$query = $this->qls->SQL->select('*', 'app_object_peer');
		while($row = $this->qls->SQL->fetch_assoc($query)) {
			
			$rowID = $row['id'];
			
			// ObjectA Data
			$aID = $row['a_id'];
			$aFace = $row['a_face'];
			$aDepth = $row['a_depth'];
			$aPort = $row['a_port'];
			$aEndpoint = $row['a_endpoint'];
			
			// ObjectB Data
			$bID = $row['b_id'];
			$bFace = $row['b_face'];
			$bDepth = $row['b_depth'];
			$bPort = $row['b_port'];
			$bEndpoint = $row['b_endpoint'];
			
			if(!isset($this->peerArray[$aID][$aFace][$aDepth])) {
				$this->peerArray[$aID][$aFace][$aDepth] = array(
					'id' => $rowID,
					'selfPort' => $aPort,
					'selfEndpoint' => $aEndpoint,
					'peerID' => $bID,
					'peerFace' => $bFace,
					'peerDepth' => $bDepth,
					'peerEndpoint' => $bEndpoint,
					'floorplanPeer' => $row['floorplan_peer']
				);
			}
			
			if($row['floorplan_peer']) {
				if(!isset($this->peerArray[$aID][$aFace][$aDepth]['peerArray'][$bID][$bFace][$bDepth])) {
					$this->peerArray[$aID][$aFace][$aDepth]['peerArray'][$bID][$bFace][$bDepth] = array();
				}
				$this->peerArray[$aID][$aFace][$aDepth]['peerArray'][$bID][$bFace][$bDepth][$rowID] = array((int)$aPort, (int)$bPort);
			}
			
			if(!isset($this->peerArray[$bID][$bFace][$bDepth])) {
				$this->peerArray[$bID][$bFace][$bDepth] = array(
					'id' => $rowID,
					'selfPort' => $bPort,
					'selfEndpoint' => $bEndpoint,
					'peerID' => $aID,
					'peerFace' => $aFace,
					'peerDepth' => $aDepth,
					'peerEndpoint' => $aEndpoint,
					'floorplanPeer' => $row['floorplan_peer']
				);
			}
			
			if($row['floorplan_peer']) {
				if(!isset($this->peerArray[$bID][$bFace][$bDepth]['peerArray'][$aID][$aFace][$aDepth])) {
					$this->peerArray[$bID][$bFace][$bDepth]['peerArray'][$aID][$aFace][$aDepth] = array();
				}
				$this->peerArray[$bID][$bFace][$bDepth]['peerArray'][$aID][$aFace][$aDepth][$rowID] = array((int)$bPort, (int)$aPort);
			}
			
			if(!$row['floorplan_peer']) {
				$this->peerArrayStandard[$aID][$aFace][$aDepth] = array(
					'rowID' => $rowID,
					'selfEndpoint' => $aEndpoint,
					'id' => $bID,
					'face' => $bFace,
					'depth' => $bDepth,
					'port' => false,
					'endpoint' => $bEndpoint,
					'floorplanPeer' => $row['floorplan_peer']
				);
				$this->peerArrayStandard[$bID][$bFace][$bDepth] = array(
					'rowID' => $rowID,
					'selfEndpoint' => $bEndpoint,
					'id' => $aID,
					'face' => $aFace,
					'depth' => $aDepth,
					'port' => false,
					'endpoint' => $aEndpoint,
					'floorplanPeer' => $row['floorplan_peer']
				);
			} else {
				if(!isset($this->peerArrayWalljack[$aID])) {
					$this->peerArrayWalljack[$aID] = array();
				}
				array_push($this->peerArrayWalljack[$aID], array(
					'rowID' => $rowID,
					'selfID' => $aID,
					'selfPortID' => $aPort,
					'id' => $bID,
					'face' => $bFace,
					'depth' => $bDepth,
					'port' => $bPort,
					'endpoint' => $bEndpoint,
					'floorplanPeer' => $row['floorplan_peer']
				));
				
				$this->peerArrayWalljackEntry[$aID][$aFace][$aDepth][$aPort] = array(
					'rowID' => $rowID,
					'id' => $bID,
					'face' => $bFace,
					'depth' => $bDepth,
					'port' => $bPort,
					'endpoint' => $bEndpoint,
					'floorplanPeer' => $row['floorplan_peer']
				);
				
				$this->peerArrayStandardFloorplan[$bID][$bFace][$bDepth][$bPort] = array(
					'rowID' => $rowID,
					'id' => $aID,
					'face' => $aFace,
					'depth' => $aDepth,
					'port' => $aPort,
					'endpoint' => $aEndpoint,
					'floorplanPeer' => $row['floorplan_peer']
				);
			}
		}
		
		
		// History Action Type
		$this->historyActionTypeArray = array();
		$query = $qls->SQL->select('*', 'shared_history_action_type');
		while($row = $qls->SQL->fetch_assoc($query)) {
			$this->historyActionTypeArray[$row['value']] = $row;
		}
		
		// History Function
		$this->historyFunctionArray = array();
		$query = $qls->SQL->select('*', 'shared_history_function');
		while($row = $qls->SQL->fetch_assoc($query)) {
			$this->historyFunctionArray[$row['value']] = $row;
		}
		
	}
	
	function generateObjectPortName($objID, $objFace, $objDepth, $objPort) {
		if($objID == 0) {
			$objectPortName = 'None';
		} else {
			$obj = $this->objectArray[$objID];
			$objName = $obj['nameString'];
			$objTemplateID = $obj['template_id'];
			$objTemplate = $this->templateArray[$objTemplateID];
			$objCompatibility = $this->compatibilityArray[$objTemplateID][$objFace][$objDepth];
			$objTemplateType = $objTemplate['templateType'];
			$objTemplateFunction = $objCompatibility['partitionFunction'];
			
			if($objTemplateType == 'walljack') {
				if(isset($this->peerArray[$objID][$objFace][$objDepth]['peerArray'])) {
					$peerData = $this->peerArray[$objID][$objFace][$objDepth]['peerArray'];
					foreach($peerData as $peerID => $peer) {
						$peerObj = $this->objectArray[$peerID];
						$peerTemplateID = $peerObj['template_id'];
						foreach($peer as $peerFace => $partition) {
							foreach($partition as $peerDepth => $portPair) {
								foreach($portPair as $port) {
									if($port[0] == $objPort) {
										$peerCompatibility = $this->compatibilityArray[$peerTemplateID][$peerFace][$peerDepth];
										$peerPortNameFormat = json_decode($peerCompatibility['portNameFormat'], true);
										$peerPortTotal = $peerCompatibility['portTotal'];
										$peerPortID = $port[1];
										$peerPortName = $this->generatePortName($peerPortNameFormat, $peerPortID, $peerPortTotal);
										$objPortNameArray = array($objName, $peerPortName);
										$objectPortName = implode('.', $objPortNameArray);
										//$objectPortName = $objectPortName.'('.$objPort.')';
									}
								}
							}
						}
					}
				}
			} else {
				$portNameFormat = json_decode($objCompatibility['portNameFormat'], true);
				$portTotal = $objCompatibility['portTotal'];
				$objPortName = $this->generatePortName($portNameFormat, $objPort, $portTotal);
				
				if($objTemplateType == 'Insert' and $objTemplateFunction == 'Endpoint') {
					$objectPortName = $objName.$objPortName;
				} else {
					$objPortNameArray = array($objName, $objPortName);
					$objectPortName = implode('.', $objPortNameArray);
				}
			}
		}
		
		return $objectPortName;
	}

	function generateObjectName($objID, $includeTree=true, $includeInsertParentName=true) {
		$object = $this->objectArray[$objID];
		$envTreeID = $object['env_tree_id'];
		$objectTemplateID = $object['template_id'];
		$template = $this->templateArray[$objectTemplateID];
		$templateType = $template['templateType'];
		$templateFunction = $template['templateFunction'];
		$locationArray = array();
		
		// Save object name separately if it's an insert
		if($templateType == 'Insert') {
			$objName = $object['name'];
			$parentID = $object['parent_id'];
			$object = $this->objectArray[$parentID];
			$objectTemplateID = $object['template_id'];
			$template = $this->templateArray[$objectTemplateID];
			$templateType = $template['templateType'];
			if($templateType == 'Insert') {
				$templateFunction = $template['templateFunction'];
				$separator = ($templateFunction == 'Endpoint') ? '' : '.';
				$objName = $object['name'].$separator.$objName;
				$parentID = $object['parent_id'];
				$object = $this->objectArray[$parentID];
			}
			array_unshift($locationArray, $objName);
		}
		
		if($includeInsertParentName) {
			array_unshift($locationArray, $object['name']);
		}
		
		if($includeTree) {
			//Locations
			$rootTreeNode = false;
			while(!$rootTreeNode) {
				if(isset($this->envTreeArray[$envTreeID])) {
					$node = $this->envTreeArray[$envTreeID];
					$nodeName = $node['name'];
					array_unshift($locationArray, $nodeName);
					$envTreeID = $node['parent'];
					$rootTreeNode = $envTreeID == '#' ? true : false;
				} else {
					$rootTreeNode = true;
				}
			}
		}
		
		return implode('.', $locationArray);
	}
	
	/**
	 * Generates human readable port name
	 * @return string
	 */
	function generatePortName($portNameFormat, $index, $portTotal) {
		$portString = '';
		$incrementalCount = 0;
		
		// Create character arrays
		$lowercaseIncrementArray = array();
		$uppercaseIncrementArray = array();
		for($x=97; $x<=122; $x++) {
			array_push($lowercaseIncrementArray, chr($x));
		}
		for($x=65; $x<=90; $x++) {
			array_push($uppercaseIncrementArray, chr($x));
		}
		
		// Account for infinite count incrementals
		foreach($portNameFormat as &$itemA) {
			$type = $itemA['type'];
			
			if($type == 'incremental' or $type == 'series') {
				$incrementalCount++;
				if($itemA['count'] == 0) {
					$itemA['count'] = $portTotal;
				}
			}
		}
		
		foreach($portNameFormat as $itemB) {
			$type = $itemB['type'];
			$value = $itemB['value'];
			$order = $itemB['order'];
			$count = $itemB['count'];
			
			if($type == 'static') {
				$portString = $portString.$value;
			} else if($type == 'incremental' or $type == 'series') {
				$numerator = 1;
				if($order < $incrementalCount) {
					foreach($portNameFormat as $itemC) {
						$typeC = $itemC['type'];
						$orderC = $itemC['order'];
						$countC = $itemC['count'];
						
						if($typeC == 'incremental' or $typeC == 'series') {
							if($order < $orderC) {
								$numerator *= $countC;
							}
						}
					}
				}
				
				$howMuchToIncrement = floor($index / $numerator);
				
				if($howMuchToIncrement >= $count) {
					$rollOver = floor($howMuchToIncrement / $count);
					$howMuchToIncrement = $howMuchToIncrement - ($rollOver * $count);
				}
				
				if($type == 'incremental') {
					if(is_numeric($value)) {
						$value = $value + $howMuchToIncrement;
						$portString = $portString.$value;
					} else {
						$asciiValue = ord($value);
						$asciiIndex = $asciiValue + $howMuchToIncrement;
						if($asciiValue >= 65 && $asciiValue <= 90) {
							// Uppercase
							
							while($asciiIndex > 90) {
								$portString = $portString.$uppercaseIncrementArray[0];
								$asciiIndex -= 26;
							}
							$portString = $portString.$uppercaseIncrementArray[$asciiIndex-65];
						} else if($asciiValue >= 97 && $asciiValue <= 122) {
							// Lowercase
							while($asciiIndex > 122) {
								$portString = $portString.$lowercaseIncrementArray[0];
								$asciiIndex -= 26;
							}
							$portString = $portString.$lowercaseIncrementArray[$asciiIndex-97];
						}
					}
					
				} else if($type == 'series') {
					$portString = $portString.$value[$howMuchToIncrement];
				}
			}
		}
			
		return $portString;
	}

	/**
	 * Generates unique object name
	 * @return string
	 */
	function findUniqueName($parentID, $nameType, $name=false){
		for($count=0; $count<10; $count++) {
			$uniqueNameValue = $this->generateUniqueNameValue();
			
			// Search for duplicate name
			if($nameType == 'object') {
				$uniqueName = NEW_OBJECT_PREFIX.$uniqueNameValue;
				$query = $this->qls->SQL->select('*', 'app_object', array('env_tree_id' => array('=', $parentID), 'AND', 'name' => array('=', $uniqueName)));
			} else if($nameType == 'template') {
				$uniqueName = $name.'_'.$uniqueNameValue;
				$query = $this->qls->SQL->select('*', 'app_object_templates', array('templateName' => array('=', $uniqueName)));
			} else {
				if($nameType == 'location') {
					$uniqueName = NEW_LOCATION_PREFIX.$uniqueNameValue;
				} else if($nameType == 'pod') {
					$uniqueName = NEW_POD_PREFIX.$uniqueNameValue;
				} else if($nameType == 'cabinet') {
					$uniqueName = NEW_CABINET_PREFIX.$uniqueNameValue;
				} else if($nameType == 'floorplan') {
					$uniqueName = NEW_FLOORPLAN_PREFIX.$uniqueNameValue;
				} else {
					return false;
				}
				$query = $this->qls->SQL->select('*', 'app_env_tree', array('parent' => array('=', $parentID), 'AND', 'name' => array('=', $uniqueName)));
			}
			if(!$this->qls->SQL->num_rows($query)) {
				return $uniqueName;
			}
		}
		return false;
	}
	
	/**
	 * Generates unique name value
	 * @return string
	 */
	function generateUniqueNameValue(){
		$characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$length = 4;
		$charactersLength = strlen($characters);
		$uniqueNameValue = '';
		for($i = 0; $i < $length; $i++) {
			$uniqueNameValue .= $characters[rand(0, $charactersLength - 1)];
		}
		return $uniqueNameValue;
	}
	
	/**
	 * Builds information about an object
	 * @return array
	 */
	function getObject($objID, $portID=0, $objFace=0, $objDepth=0, $incPortName=true){
		$return = array(
			'obj' => array(),
			'function' => '',
			'id' => $objID,
			'selected' => false,
			'nameString' => ''
		);
		
		//Build the object
		if(isset($this->objectArray[$objID])) {
			$obj = $this->objectArray[$objID];
		} else {
			$return['id'] = 0;
			return $return;
		}
		
		$templateID = $obj['template_id'];
		$categoryID = $this->templateArray[$templateID]['templateCategory_id'];
		$return['categoryID'] = $categoryID;
		$objCompatibility = $this->compatibilityArray[$templateID][$objFace][$objDepth];
		$templateType = $objCompatibility['templateType'];

		// Retrieve port info
		if($incPortName) {
			
			if($templateType == 'walljack') {
				$peerEntry = $this->peerArrayWalljackEntry[$objID][$objFace][$objDepth][$portID];
				$portID = $peerEntry['port'];
				$peerObj = $this->objectArray[$peerEntry['id']];
				$peerTemplateID = $peerObj['template_id'];
				$objCompatibility = $this->compatibilityArray[$peerTemplateID][$peerEntry['face']][$peerEntry['depth']];
			} else if($templateType == 'wap' or $templateType == 'device'){
				$portName = 'NIC1';
			}
			
			$return['function'] = $objCompatibility['partitionFunction'];
			$portNameFormat = json_decode($objCompatibility['portNameFormat'], true);
			$portTotal = $objCompatibility['portLayoutX']*$objCompatibility['portLayoutY'];
			$portName = $this->generatePortName($portNameFormat, $portID, $portTotal);
			$templateType = $objCompatibility['templateType'];
			
			if($templateType == 'Insert') {
				$separator = $return['function'] == 'Passive' ? '.' : ''; 
				$portName = $obj['name'] == '' ? $portName : $obj['name'].$separator.$portName;
			}
			array_unshift($return['obj'], $portName);
		}
		
		if($templateType == 'Insert') {
			$query = $this->qls->SQL->select('*', 'app_object', array('id' => array('=', $obj['parent_id'])));
			$obj = $this->qls->SQL->fetch_assoc($query);
		}
		
		$side = '';
		if($this->templateArrray[$obj['template_id']]['templateMountConfig'] == 1){
			$side = $objFace == 0 ? '(front)' : '(back)';
		}
		
		array_unshift($return['obj'], $obj['name']);
		
		$objParentID = $obj['env_tree_id'];
		
		while($objParentID != '#'){
			$obj = $this->envTreeArray[$objParentID];
			array_unshift($return['obj'], $obj['name']);
			$objParentID = $obj['parent'];
		}
		
		$return['nameString'] = implode('.', $return['obj']);
		
		return $return;
	}
	
	function getPortNameString($objID, $objFace, $objDepth, $portID){
		
		$nameArray = array();
		$objectArray = $this->objectArray;
		$compatibilityArray = $this->compatibilityArray;
		$templateArray = $this->templateArray;
		$envTreeArray = $this->envTreeArray;
		$peerArrayWalljackEntry = $this->peerArrayWalljackEntry;
		
		$obj = $objectArray[$objID];
		$templateID = $obj['template_id'];
		$objCompatibility = $compatibilityArray[$templateID][$objFace][$objDepth];
		$template = $this->templateArray[$templateID];
		$templateType = $template['templateType'];
		
		if($templateType == 'walljack') {
			$peerEntry = $peerArrayWalljackEntry[$objID][$objFace][$objDepth][$portID];
			$portID = $peerEntry['port'];
			$peerObj = $objectArray[$peerEntry['id']];
			$peerTemplateID = $peerObj['template_id'];
			$objCompatibility = $compatibilityArray[$peerTemplateID][$peerEntry['face']][$peerEntry['depth']];
			$template = $this->templateArray[$peerTemplateID];
			$templateType = $template['templateType'];
		}
		
		$return['function'] = $objCompatibility['partitionFunction'];
		$portNameFormat = json_decode($objCompatibility['portNameFormat'], true);
		
		$portTotal = $objCompatibility['portLayoutX']*$objCompatibility['portLayoutY'];
		$portName = $this->generatePortName($portNameFormat, $portID, $portTotal);
		
		
		if($templateType == 'Insert') {
			
			$objParentID = $obj['parent_id'];
			$separator = ($return['function'] == 'Passive') ? '.' : '';
			$nestedParentHUnits = $template['nestedParentHUnits'];
			$nestedParentVUnits = $template['nestedParentVUnits'];
			if(isset($nestedParentHUnits) and isset($nestedParentVUnits)) {
				$objParent = $this->objectArray[$objParentID];
				$objName = $objParent['name'].$separator.$obj['name'];
				$objParentID = $objParent['parent_id'];
			} else {
				$objName = $objName;
			}
			$portName = ($obj['name'] == '') ? $portName : $objName.$separator.$portName;
			
			$obj = $objectArray[$objParentID];
		}
		
		array_unshift($nameArray, $portName);
		
		$side = '';
		if($templateArray[$obj['template_id']]['templateMountConfig'] == 1){
			$side = $objFace == 0 ? '(front)' : '(back)';
		}
		
		array_unshift($nameArray, $obj['name']);
		
		$objParentID = $obj['env_tree_id'];
		
		while($objParentID != '#'){
			$obj = $envTreeArray[$objParentID];
			array_unshift($nameArray, $obj['name']);
			$objParentID = $obj['parent'];
		}
		
		foreach($nameArray as $index => $element) {
			if($index < (count($nameArray)-1)) {
				$portNameString .= $element.'.';
			} else {
				$portNameString .= $element;
			}
		}
		
		return $portNameString;
	}
	
	/**
	 * Find object peer
	 * @return array
	 */
	function findPeer($objID, $objFace, $objDepth, $objPort){
		$obj = $this->objectArray[$objID];
		$objTemplate = $this->templateArray[$obj['template_id']];
		
		// Find peer or return if nothing found
		if($objTemplate['templateType'] == 'walljack' or $objTemplate['templateType'] == 'wap') {
			return $this->peerArrayWalljackEntry[$objID][$objFace][$objDepth][$objPort];
		} else {
			if(isset($this->peerArrayStandard[$objID][$objFace][$objDepth])) {
				$peer = $this->peerArrayStandard[$objID][$objFace][$objDepth];
			} else if(isset($this->peerArrayStandardFloorplan[$objID][$objFace][$objDepth][$objPort])) {
				$peer = $this->peerArrayStandardFloorplan[$objID][$objFace][$objDepth][$objPort];
			}
			return $peer;
		}
	}
	
	function retrievePorts($objID, $objFace, $objDepth, $objPort) {
		$obj = $this->objectArray[$objID];
		$templateID = $obj['template_id'];
		$template = $this->templateArray[$templateID];
		$objType = $template['templateType'];
		$portOptions = '';
		
		if($objType == 'walljack') {
			foreach($this->peerArrayWalljack[$objID] as $peerEntry) {
				$peerID = $peerEntry['id'];
				$peerFace = $peerEntry['face'];
				$peerDepth = $peerEntry['depth'];
				$peerPortID = $peerEntry['port'];
				$selfPortID = $peerEntry['selfPortID'];
				$walljackPortID = $peerEntry['selfPortID'];
				$peerObj = $this->objectArray[$peerID];
				$peerTemplateID = $peerObj['template_id'];
				$peerTemplate = $this->templateArray[$peerTemplateID];
				$objType = $peerTemplate['templateType'];
				$objCompatibility = $this->compatibilityArray[$peerTemplateID][$peerFace][$peerDepth];
				$partitionFunction = $objCompatibility['partitionFunction'];
				$portNameFormat = json_decode($objCompatibility['portNameFormat'], true);
				$portTotal = $objCompatibility['portLayoutX']*$objCompatibility['portLayoutY'];
				$objName = $peerObj['name'];
				
				// Nested insert object name
				if($objType == 'Insert') {
					$nestedParentHUnits = $template['nestedParentHUnits'];
					$nestedParentVUnits = $template['nestedParentVUnits'];
					if(isset($nestedParentHUnits) and isset($nestedParentVUnits)) {
						$parentObjID = $obj['parent_id'];
						$parentObj = $this->objectArray[$parentObjID];
						$parentObjName = $parentObj['name'];
						$objName = ($partitionFunction == 'Endpoint') ? $parentObjName.$objName : $parentObjName.'.'.$objName;
					}
				}
				
				$portFlags = $this->getPortFlags($objID, $objFace, $objDepth, $selfPortID);
				$selected = $walljackPortID == $objPort ? ' selected' : '';
				$portName = $this->generatePortName($portNameFormat, $peerPortID, $portTotal);
				$portString = ($objType == 'Insert' and $partitionFunction == 'Endpoint') ? $objName.$portName : $portName;
				$portOptions .= '<option value="'.$walljackPortID.'"'.$selected.'>'.$portString.$portFlags.'</option>';
			}
		} else {
			$objCompatibility = $this->compatibilityArray[$templateID][$objFace][$objDepth];
			$partitionFunction = $objCompatibility['partitionFunction'];
			$portNameFormat = json_decode($objCompatibility['portNameFormat'], true);
			$portTotal = $objCompatibility['portLayoutX']*$objCompatibility['portLayoutY'];
			$objName = $obj['name'];
			$separator = ($partitionFunction == 'Endpoint') ? '' : '.';
			
			// Nested insert object name
			if($objType == 'Insert') {
				$nestedParentHUnits = $template['nestedParentHUnits'];
				$nestedParentVUnits = $template['nestedParentVUnits'];
				if(isset($nestedParentHUnits) and isset($nestedParentVUnits)) {
					$parentObjID = $obj['parent_id'];
					$parentObj = $this->objectArray[$parentObjID];
					$parentObjName = $parentObj['name'];
					$objName = $parentObjName.$separator.$objName;
				}
			}
			
			for($x=0; $x<$portTotal; $x++) {
				$portFlags = $this->getPortFlags($objID, $objFace, $objDepth, $x);
				$selected = $x == $objPort ? ' selected' : '';
				$portName = $this->generatePortName($portNameFormat, $x, $portTotal);
				$portString = ($objType == 'Insert') ? $objName.$separator.$portName : $portName;
				$portOptions .= '<option value="'.$x.'"'.$selected.'>'.$portString.$portFlags.'</option>';
			}
		}
		
		return $portOptions;
	}

	function buildTreePathString($nodeID){
		$node = $this->envTreeArray[$nodeID];
		$nodeName = $node['name'];
		$nodeParentID = $node['parent'];
		$treePathArray = array($nodeName);
		
		while($nodeParentID != '#') {
			$node = $this->envTreeArray[$nodeParentID];
			$nodeName = $node['name'];
			$nodeParentID = $node['parent'];
			array_unshift($treePathArray, $nodeName);
		}
		
		$treePath = implode('.', $treePathArray);
		return $treePath;
	}
	
	function buildTreeLocation(){
		$treeArray = array();
		$treeSort = $this->qls->user_info['treeSort'];
		$treeSortAdj = $this->qls->user_info['treeSortAdj'];
		$counter = 1;
		$visitedNodeArray = array();
		
		if($treeSort == 0) {
			$query = $this->qls->SQL->select('*', 'app_env_tree', false, array('name', 'ASC'));
		} else if($treeSort == 1) {
			$query = $this->qls->SQL->select('*', 'app_env_tree', false, array('order', 'ASC'));
		}
		
		while($envNode = $this->qls->SQL->fetch_assoc($query)) {
			
			$nodeID = $envNode['id'];
			$nodeName = $envNode['name'];
			$nodeParent = $envNode['parent'];
			$nodeType = $envNode['type'];
			
			if($nodeType == 'location' || $nodeType == 'pod') {
				$elementType = 0;
			} else if($nodeType == 'cabinet' || $nodeType == 'floorplan') {
				$elementType = 1;
			}
			
			$value = array($elementType, $nodeID, 0, 0, 0);
			$value = implode('-', $value);
			
			$nodeEntry = array(
				'id' => $nodeID,
				'order' => $counter,
				'text' => $nodeName,
				'parent' => $nodeParent,
				'type' => $nodeType,
				'data' => array('globalID' => $value)
			);
			
			if($treeSortAdj == 1) {
				// Does node have adjacency?
				if(isset($this->cabinetAdjacencyArrayFixed[$nodeID]) and !in_array($nodeID, $visitedNodeArray)) {
					$adjNodeID = $nodeID;
					// Find the left most cabinet
					while($this->cabinetAdjacencyArrayFixed[$adjNodeID]['left'] != 0) {
						$adjNodeID = $this->cabinetAdjacencyArrayFixed[$adjNodeID]['left'];
					}
					// Add adjacent cabinet series in order
					while($adjNodeID != 0) {
						
						$left = $this->cabinetAdjacencyArrayFixed[$adjNodeID]['left'];
						$right = $this->cabinetAdjacencyArrayFixed[$adjNodeID]['right'];
						if($left == 0 and $right != 0) {
							$adjIndicator = '&downarrow;';
						} else if($left != 0 and $right != 0) {
							$adjIndicator = '&updownarrow;';
						} else if($left != 0 and $right == 0) {
							$adjIndicator = '&uparrow;';
						} else {
							$adjIndicator = '';
						}
						
						$node = $this->envTreeArray[$adjNodeID];
						$nodeName = $adjIndicator.$node['name'];
						$nodeParent = $node['parent'];
						$nodeType = $node['type'];
						
						if($nodeType == 'location' || $nodeType == 'pod') {
							$elementType = 0;
						} else if($nodeType == 'cabinet' || $nodeType == 'floorplan') {
							$elementType = 1;
						}
						
						$value = array($elementType, $adjNodeID, 0, 0, 0);
						$value = implode('-', $value);
						
						$nodeEntry = array(
							'id' => $adjNodeID,
							'order' => $counter,
							'text' => $nodeName,
							'parent' => $nodeParent,
							'type' => $nodeType,
							'data' => array('globalID' => $value)
						);
						
						// Add node
						$treeArray[] = $nodeEntry;
						$counter++;
						array_push($visitedNodeArray, $adjNodeID);
						$adjNodeID = $this->cabinetAdjacencyArrayFixed[$adjNodeID]['right'];
					}
				} else {
					if(!in_array($nodeID, $visitedNodeArray)) {
						// Add node
						$treeArray[] = $nodeEntry;
						$counter++;
						array_push($visitedNodeArray, $nodeID);
					}
				}
			} else {
				// Add node
				$treeArray[] = $nodeEntry;
				$counter++;
			}
		}
		return $treeArray;
	}
	
	function buildTreeObjects($cabinetID){
		$treeArray = array();
		$objSort = $this->qls->user_info['objSort'];
		
		if($objSort == 0) {
			// Sort objects by name
			$objSortAttr = 'name';
			$objSortDir = 'ASC';
		} else {
			// Sort objects by RU
			$objSortAttr = 'RU';
			$cabinet = $this->envTreeArray[$cabinetID];
			$cabinetRUOrientation = $cabinet['ru_orientation'];
			if($cabinetRUOrientation == 0) {
				$objSortDir = 'DESC';
			} else {
				$objSortDir = 'ASC';
			}
		}
		
		$query = $this->qls->SQL->select('*', 'app_object', array('env_tree_id' => array('=', $cabinetID)), array($objSortAttr, $objSortDir));
		
		$counter = 1;
		while($objectNode = $this->qls->SQL->fetch_assoc($query)) {
			if($objectNode['parent_id'] == 0 or $objectNode['parent_id'] == Null) {
				$objectID = $objectNode['id'];
				$objectName = $objectNode['name'];
				$objectTemplateID = $objectNode['template_id'];
				$objectTemplate = $this->templateArray[$objectTemplateID];
				$objectType = $objectTemplate['templateType'];
				
				$value = array(2, $objectID, 0, 0, 0);
				$value = implode('-', $value);
				
				array_push($treeArray, array(
					'id' => 'O'.$objectID,
					'order' => $counter,
					'text' => $objectName,
					'parent' => $cabinetID,
					'type' => 'object',
					'objectType' => $objectType,
					'data' => array('globalID' => $value, 'objectID' => $objectID)
				));
			}
			$counter++;
		}
		
		return $treeArray;
	}
	
	function buildTreePorts($nodeID, $objectCompatibility, $cablePortType, $cableMediaType, $forTrunk=false){
		
		$treeArray = array();
		$element = $this->objectArray[$nodeID];
		$templateID = $element['template_id'];
		$template = $this->templateArray[$templateID];
		$templateFunction = $template['templateFunction'];
		
		$elementArray = array();
		if(($forTrunk and $templateFunction != 'Endpoint') or !$forTrunk) {
			
			// Iterate over selected object partitions
			if(isset($this->compatibilityArray[$templateID])) {
				foreach($this->compatibilityArray[$templateID] as $nodeFace => $nodeFaceObj) {
					foreach($nodeFaceObj as $nodeDepth => $partition) {
						
						$partitionType = $partition['partitionType'];
						$partitionFunction = $partition['partitionFunction'];
						$templateType = $partition['templateType'];
						if($partitionType == 'Enclosure') {
							
							// Iterate over enclosure inserts
							if(isset($this->insertAddressArray[$nodeID][$nodeFace][$nodeDepth])) {
								foreach($this->insertAddressArray[$nodeID][$nodeFace][$nodeDepth] as $slotX => $encRow) {
									foreach($encRow as $slotY => $insert) {
										
										$insertID = $insert['id'];
										$insertName = $insert['name'];
										$insertTemplateID = $insert['template_id'];
										if(isset($this->compatibilityArray[$insertTemplateID])) {
											foreach($this->compatibilityArray[$insertTemplateID] as $insertFace => $insertFaceObj) {
												foreach($insertFaceObj as $insertDepth => $insertPartition) {
													
													// Cannot be a trunked endpoint
													if(!$this->peerArrayStandard[$insertID][0][$insertDepth]['selfEndpoint']) {
														$separator = $insertPartition['partitionFunction'] == 'Endpoint' ? '' : '.';
														$insertPartition['objectID'] = $insertID;
														$insertPartition['portNamePrefix'] = $insertName == '' ? '' : $insertName.$separator;
														array_push($elementArray, $insertPartition);
													}
												}
											}
										}
									}
								}
							}
							
						} else if($templateType == 'Insert') {
							
							// Cannot be a trunked endpoint
							if(!$this->peerArrayStandard[$nodeID][$nodeFace][$nodeDepth]['selfEndpoint']) {
								$separator = $partitionFunction == 'Endpoint' ? '' : '.';
								$rowPartitionElement = $partition;
								$rowPartitionElement['objectID'] = $nodeID;
								$rowPartitionElement['portNamePrefix'] = $element['name'] == '' ? '' : $element['name'].$separator;
								array_push($elementArray, $rowPartitionElement);
							}
						} else {
							
							// Cannot be a trunked endpoint
							if(!$this->peerArrayStandard[$nodeID][$nodeFace][$nodeDepth]['selfEndpoint']) {
								$rowPartitionElement = $partition;
								$rowPartitionElement['objectID'] = $nodeID;
								$rowPartitionElement['portNamePrefix'] = '';
								array_push($elementArray, $rowPartitionElement);
							}
						}
					}
				}
			}
		}
		
		foreach($elementArray as $elementItem) {
			$elementPortType = $elementItem['portType'];
			$elementMediaCategory = $elementItem['mediaCategory'];
			$elementMediaCategoryType = $elementItem['mediaCategoryType'];
			$elementPartitionFunction = $elementItem['partitionFunction'];
			
			if($cablePortType) {
				
				$cableMediaCategory = $this->mediaTypeByIDArray[$cableMediaType]['category_id'];
				
				// Media category must be compatible (Copper, Singlmode, Multimode)
				if($elementMediaCategory == $cableMediaCategory and $elementPortType == $cablePortType) {
					$isCompatible = true;
					
				} else if($elementPartitionFunction == 'Endpoint' and $elementPortType == $cablePortType) {
					$isCompatible = true;
					
				} else if($elementPortType == 4) {
					$isCompatible = true;
					
				} else {
					$isCompatible = false;
				}
				
			} else if($objectCompatibility) {
				
				$objectMediaCategory = $objectCompatibility['mediaCategory'];
				$objectMediaCategoryType = $objectCompatibility['mediaCategoryType'];
				$objectPortType = $objectCompatibility['portType'];
				$objectPartitionFunction = $objectCompatibility['partitionFunction'];
				
				// Media category must be compatible (Copper, Singlmode, Multimode)
				if($elementMediaCategory == $objectMediaCategory) {
					$isCompatible = true;
					
				// Port type must be compatible
				} else if($elementMediaCategoryType == $objectMediaCategoryType and ($elementPartitionFunction == 'Endpoint' or $objectPartitionFunction == 'Endpoint')) {
					$isCompatible = true;
					
				// If either port type is SFP, then they are compatible
				} else if($elementPortType == 4 or $objectPortType == 4) {
					$isCompatible = true;
					
				// Failing all of that, not compatible
				} else {
					$isCompatible = false;
				}
				
			}
			
			if($forTrunk and isset($this->peerArrayStandard[$nodeID][$elementItem['side']][$elementItem['depth']])) {
				$isCompatible = false;
			}
			
			if($isCompatible) {
				if($elementItem['templateType'] == 'walljack') {
					if(isset($this->peerArrayWalljack[$nodeID])) {
						foreach($this->peerArrayWalljack[$nodeID] as $peerEntry) {
							$peerID = $peerEntry['id'];
							$peerFace = $peerEntry['face'];
							$peerDepth = $peerEntry['depth'];
							$peerPort = $peerEntry['port'];
							$selfPortID = $peerEntry['selfPortID'];
							$peerObject = $this->objectArray[$peerID];
							$peerTemplateID = $peerObject['template_id'];
							$peerCompatibility = $this->compatibilityArray[$peerTemplateID][$peerFace][$peerDepth];
							$portTotal = $peerCompatibility['portLayoutX'] * $peerCompatibility['portLayoutY'];
							$portNameFormat = json_decode($peerCompatibility['portNameFormat'], true);
							
							$flagString = $this->getPortFlags($nodeID, 0, 0, $selfPortID);
							$portName = $this->generatePortName($portNameFormat, $peerPort, $portTotal);
							$portName = $portName.$flagString;
							
							$value = array(
								4,
								$nodeID,
								0,
								0,
								$selfPortID
							);
							$value = implode('-', $value);
							
							array_push($treeArray, array(
								'id' => $value,
								'text' => $portName,
								'parent' => 'O'.$nodeID,
								'type' => 'port',
								'data' => array('globalID' => $value)
							));
						}
					}
				} else {
					$portNameFormat = json_decode($elementItem['portNameFormat'], true);
					$portTotal = $elementItem['portLayoutX']*$elementItem['portLayoutY'];
					
					for($x=0; $x<$portTotal; $x++) {
						$flagString = $this->getPortFlags($elementItem['objectID'], $elementItem['side'], $elementItem['depth'], $x);
						$portName = $this->generatePortName($portNameFormat, $x, $portTotal);
						$portName = $elementItem['portNamePrefix'].$portName.$flagString;
						
						
						$value = array(
							4,
							$elementItem['objectID'],
							$elementItem['side'],
							$elementItem['depth'],
							$x
						);
						$value = implode('-', $value);
						
						array_push($treeArray, array(
							'id' => $value,
							'text' => $portName,
							'parent' => 'O'.$nodeID,
							'type' => 'port',
							'data' => array('globalID' => $value)
						));
					}
				}
				
			}
		}
		
		return $treeArray;
	}
	
	function buildTreePorts2($objID, $objFace, $objDepth, $elementID, $forTrunk=false, $cablePortType=false, $cableMediaType=false) {
		
		$treeArray = array();
		$element = $this->objectArray[$elementID];
		$elementTemplateID = $element['template_id'];
		$elementTemplate = $this->templateArray[$elementTemplateID];
		$elementTemplateFunction = $elementTemplate['templateFunction'];
		
		$object = $this->objectArray[$objID];
		$objectTemplateID = $object['template_id'];
		$objectTemplate = $this->templateArray[$objectTemplateID];
		$objectTemplateFunction = $objectTemplate['templateFunction'];
		$objectCompatibility = $this->compatibilityArray[$objectTemplateID][$objFace][$objDepth];
		$objectPortType = $objectCompatibility['portType'];
		$objectMediaType = $objectCompatibility['mediaType'];
		$objectPortTotal = $objectCompatibility['portTotal'];
		
		$elementArray = array();
		if(($forTrunk and $elementTemplateFunction != 'Endpoint') or !$forTrunk) {
			
			// Iterate over element partitions
			if(isset($this->compatibilityArray[$elementTemplateID])) {
				foreach($this->compatibilityArray[$elementTemplateID] as $elementFace => $elementFaceObj) {
					foreach($elementFaceObj as $elementDepth => $elementPartition) {
						
						$elementPartitionType = $elementPartition['partitionType'];
						$elementPartitionFunction = $elementPartition['partitionFunction'];
						$elementTemplateType = $elementPartition['templateType'];
						
						if($elementPartitionType == 'Enclosure') {
							$insertParentID = $elementID;
							$insertParentFace = $elementFace;
							$insertParentDepth = $elementDepth;
							
							// Does this partition contain inserts?
							while(isset($this->insertAddressArray[$insertParentID][$insertParentFace][$insertParentDepth])) {
								
								// Loop through partition inserts
								foreach($this->insertAddressArray[$insertParentID][$insertParentFace][$insertParentDepth] as $slotXID => $slotX) {
									foreach($slotX as $slotYID => $insert) {
										
										$insertID = $insert['id'];
										$insertName = $insert['name'];
										$insertTemplateID = $insert['template_id'];
										$insertParentID = $insert['parent_id'];
										
										// Ensure there is a compatibility entry
										if(isset($this->compatibilityArray[$insertTemplateID])) {
											
											// Loop through insert partitions
											foreach($this->compatibilityArray[$insertTemplateID] as $insertFace => $insertFaceObj) {
												foreach($insertFaceObj as $insertDepth => $insertPartition) {
													
													// Does this insert partition contain inserts?
													if(isset($this->insertAddressArray[$insertID][$insertFace][$insertDepth])) {
														
														// Insert partition is parent to a nested insert
														$insertParentID = $insertID;
														$insertParentFace = $insertFace;
														$insertParentDepth = $insertDepth;
													} else {
														
														// Cannot be a trunked endpoint
														if(!$this->peerArrayStandard[$insertID][$insertFace][$insertDepth]['selfEndpoint']) {
															$separator = ($elementPartitionFunction == 'Endpoint') ? '' : '.';
															if($insertParentID) {
																$insertParent = $this->objectArray[$insertParentID];
																$insertParentName = $insertParent['name'];
																$portNamePrefix = $insertParentName.$separator.$insertName.$separator;
															} else {
																$portNamePrefix = $insertName.$separator;
															}
															$rowPartitionElement = $insertPartition;
															$rowPartitionElement['objectID'] = $insertID;
															$rowPartitionElement['portNamePrefix'] = $portNamePrefix;
															array_push($elementArray, $rowPartitionElement);
														}
														
														$insertParentID = $insertParentFace = $insertParentDepth = 0;
													}
												}
											}
										} else {
											// This shouldn't happen, but break the loop if it does!
											$insertParentID = $insertParentFace = $insertParentDepth = 0;
										}
									}
								}
							}
							
						} else if($elementTemplateType == 'Insert') {
							
							// Cannot be a trunked endpoint
							if(!$this->peerArrayStandard[$elementID][$elementFace][$elementDepth]['selfEndpoint']) {
								$separator = ($elementPartitionFunction == 'Endpoint') ? '' : '.';
								$rowPartitionElement = $elementPartition;
								$rowPartitionElement['objectID'] = $elementID;
								$rowPartitionElement['portNamePrefix'] = $element['name'] == '' ? '' : $element['name'].$separator;
								array_push($elementArray, $rowPartitionElement);
							}
						} else {
							
							// Cannot be a trunked endpoint
							if(!$this->peerArrayStandard[$elementID][$elementFace][$elementDepth]['selfEndpoint']) {
								$rowPartitionElement = $elementPartition;
								$rowPartitionElement['objectID'] = $elementID;
								$rowPartitionElement['portNamePrefix'] = '';
								array_push($elementArray, $rowPartitionElement);
							}
						}
					}
				}
			}
		}
		
		foreach($elementArray as $elementItem) {
			
			$elementItemID = $elementItem['objectID'];
			$elementPortType = $elementItem['portType'];
			$elementMediaCategory = $elementItem['mediaCategory'];
			$elementMediaCategoryType = $elementItem['mediaCategoryType'];
			$elementPartitionFunction = $elementItem['partitionFunction'];
			
			if($cablePortType) {
				
				$cableMediaCategory = $this->mediaTypeByIDArray[$cableMediaType]['category_id'];
				
				// Media category must be compatible (Copper, Singlmode, Multimode)
				if($elementMediaCategory == $cableMediaCategory and $elementPortType == $cablePortType) {
					$isCompatible = true;
					
				} else if($elementPartitionFunction == 'Endpoint' and $elementPortType == $cablePortType) {
					$isCompatible = true;
					
				} else if($elementPortType == 4) {
					$isCompatible = true;
					
				} else {
					$isCompatible = false;
				}
				
			} else if($objectCompatibility) {
				
				$objectMediaCategory = $objectCompatibility['mediaCategory'];
				$objectMediaCategoryType = $objectCompatibility['mediaCategoryType'];
				$objectPortType = $objectCompatibility['portType'];
				$objectPartitionFunction = $objectCompatibility['partitionFunction'];
				
				// Media category must be compatible (Copper, Singlmode, Multimode)
				if($elementMediaCategory == $objectMediaCategory) {
					$isCompatible = true;
					
				// Port type must be compatible
				} else if($elementMediaCategoryType == $objectMediaCategoryType and ($elementPartitionFunction == 'Endpoint' or $objectPartitionFunction == 'Endpoint')) {
					$isCompatible = true;
					
				// If either port type is SFP, then they are compatible
				} else if($elementPortType == 4 or $objectPortType == 4) {
					$isCompatible = true;
					
				// Failing all of that, not compatible
				} else {
					$isCompatible = false;
				}
				
			}
			
			if($forTrunk and isset($this->peerArrayStandard[$elementID][$elementItem['side']][$elementItem['depth']])) {
				$isCompatible = false;
			}
			
			if($isCompatible) {
				if($elementItem['templateType'] == 'walljack') {
					if(isset($this->peerArrayWalljack[$elementID])) {
						foreach($this->peerArrayWalljack[$elementID] as $peerEntry) {
							$peerID = $peerEntry['id'];
							$peerFace = $peerEntry['face'];
							$peerDepth = $peerEntry['depth'];
							$peerPort = $peerEntry['port'];
							$selfPortID = $peerEntry['selfPortID'];
							$peerObject = $this->objectArray[$peerID];
							$peerTemplateID = $peerObject['template_id'];
							$peerCompatibility = $this->compatibilityArray[$peerTemplateID][$peerFace][$peerDepth];
							$portTotal = $peerCompatibility['portLayoutX'] * $peerCompatibility['portLayoutY'];
							$portNameFormat = json_decode($peerCompatibility['portNameFormat'], true);
							
							$flagString = $this->getPortFlags($elementID, 0, 0, $selfPortID);
							$portName = $this->generatePortName($portNameFormat, $peerPort, $portTotal);
							$portName = $portName.$flagString;
							
							$value = array(
								4,
								$elementID,
								0,
								0,
								$selfPortID
							);
							$value = implode('-', $value);
							
							array_push($treeArray, array(
								'id' => $value,
								'text' => $portName,
								'parent' => 'O'.$elementID,
								'type' => 'port',
								'data' => array('globalID' => $value)
							));
						}
					}
				} else {
					$portNameFormat = json_decode($elementItem['portNameFormat'], true);
					$portTotal = $elementItem['portLayoutX']*$elementItem['portLayoutY'];
					
					for($x=0; $x<$portTotal; $x++) {
						$flagString = $this->getPortFlags($elementItem['objectID'], $elementItem['side'], $elementItem['depth'], $x);
						$portName = $this->generatePortName($portNameFormat, $x, $portTotal);
						$portName = $elementItem['portNamePrefix'].$portName.$flagString;
						
						
						$value = array(
							4,
							$elementItem['objectID'],
							$elementItem['side'],
							$elementItem['depth'],
							$x
						);
						$value = implode('-', $value);
						
						array_push($treeArray, array(
							'id' => $value,
							'text' => $portName,
							'parent' => 'O'.$elementID,
							'type' => 'port',
							'data' => array('globalID' => $value)
						));
					}
				}
				
			}
		}
		
		return $treeArray;
	}
	
	function findCompatibleInserts($objectDetails, $elementDetails, &$treeArray, $originalParentID=0){
	
		$objectID = $objectDetails['id'];
		$objectFunction = $objectDetails['function'];
		$objectPortType = $objectDetails['portType'];
		$objectMediaType = $objectDetails['mediaType'];
		$objectPortTotal = $objectDetails['portTotal'];
		$elementID = $elementDetails['id'];
		$elementFace = $elementDetails['face'];
		$elementDepth = $elementDetails['depth'];
		$element = $this->objectArray[$elementID];
		$elementTemplateID = $element['template_id'];
		$elementTemplate = $this->templateArray[$elementTemplateID];
		$elementFunction = $elementTemplate['templateFunction'];
		
		// Select all inserts that are installed in enclosure
		if(isset($this->insertAddressArray[$elementID][$elementFace][$elementDepth])) {
			foreach($this->insertAddressArray[$elementID][$elementFace][$elementDepth] as $enclosureRow) {
				foreach($enclosureRow as $insert) {
					$insertID = $insert['id'];
					$insertTemplateID = $insert['template_id'];
					if(isset($this->compatibilityArray[$insertTemplateID])) {
						foreach($this->compatibilityArray[$insertTemplateID] as $insertFace) {
							foreach($insertFace as $insertPartition) {
								$insertPortType = $insertPartition['portType'];
								$insertMediaType = $insertPartition['mediaType'];
								$insertPortLayoutX = $insertPartition['portLayoutX'];
								$insertPortLayoutY = $insertPartition['portLayoutY'];
								$insertPortTotal = $insertPortLayoutX * $insertPortLayoutY;
								$insertFace = $insertPartition['side'];
								$insertDepth = $insertPartition['depth'];
								$insertPartitionType = $insertPartition['partitionType'];
								$insertPortNameFormat = $insertPartition['portNameFormat'];
								
								if($insertPartitionType == 'Connectable') {
									if(($objectFunction == 'Endpoint' and $elementFunction == 'Passive') or ($objectFunction == 'Passive' and $elementFunction == 'Endpoint')) {
										if(($objectPortType == 1 or $objectPortType == 4) and ($insertPortType == 1 or $insertPortType == 4)) {
											$addChild = true;
										} else {
											$addChild = false;
										}
									} else if($objectFunction == 'Passive' and $elementFunction == 'Passive') {
										if($insertMediaType == $objectMediaType) {
											$addChild = true;
										} else {
											$addChild = false;
										}
									}
									
									if($addChild and $insertPortTotal == $objectPortTotal) {
										if($insertID != $objectID) {
											$addChild = true;
										} else {
											$addChild = false;
										}
									} else {
										$addChild = false;
									}
									
									if($addChild) {
										// Check if insert is already peered
										$trunkedFlag = (isset($this->peerArray[$insertID][$insertFace][$insertDepth])) ? '*' : '';
									}
									
									if($addChild) {
										$value = array(
											3,
											$insertID,
											0,
											$insertDepth,
											0
										);
										
										$portNameFormat = json_decode($insertPortNameFormat, true);
										$firstIndex = 0;
										$lastIndex = $insertPortTotal - 1;
										$firstPortName = $this->generatePortName($portNameFormat, $firstIndex, $insertPortTotal);
										$lastPortName = $this->generatePortName($portNameFormat, $lastIndex, $insertPortTotal);
										$value = implode('-', $value);
										$includeTree = false;
										$includeInsertParentName = false;
										$insertName = $this->generateObjectName($insertID, $includeTree, $includeInsertParentName);
										array_push($treeArray, array(
											'id' => $value,
											'text' => $insertName.'.'.$firstPortName.'-'.$lastPortName.$trunkedFlag,
											'parent' => ($originalParentID != 0) ? 'O'.$originalParentID : 'O'.$elementID,
											'type' => 'port',
											'data' => array('globalID' => $value)
										));
									}
								} else if($insertPartitionType == 'Enclosure'){
									$elementDetails = array(
										'id' => $insertID,
										'face' => $insertFace,
										'depth' => $insertDepth
									);
									$originalParentID = $elementID;
									$this->findCompatibleInserts($objectDetails, $elementDetails, $treeArray, $originalParentID);
								}
							}
						}
					}
				}
			}
		}
		return;
	}
	
	function getPortFlags($objID, $objFace, $objDepth, $objPort) {
		$object = $this->objectArray[$objID];
		$objectTemplateID = $object['template_id'];
		$template = $this->templateArray[$objectTemplateID];
		$objectFunction = $template['templateFunction'];
		$flagsArray = array();
		$flagString = '';
		if(isset($this->inventoryArray[$objID][$objFace][$objDepth][$objPort])) {
			array_push($flagsArray, 'C');
		}
		
		if(isset($this->peerArrayStandard[$objID][$objFace][$objDepth]) or isset($this->peerArrayStandardFloorplan[$objID][$objFace][$objDepth][$objPort]) or isset($this->peerArrayWalljackEntry[$objID][$objFace][$objDepth][$objPort])) {
			array_push($flagsArray, 'T');
		}
		if(isset($this->populatedPortArray[$objID][$objFace][$objDepth][$objPort])) {
			array_push($flagsArray, 'P');
		}
		if(count($flagsArray)) {
			$flagString .= ' [';
			foreach($flagsArray as $index => $flag) {
				if(($index+1) == count($flagsArray)) {
					$flagString .= $flag;
				} else {
					$flagString .= $flag.',';
				}
			}
			$flagString .= ']';
		}
		return $flagString;
	}
	
	function buildConnectorFlatPath($cable, $connectorEnd){
		$returnArray = array();
		
		if($cable[$connectorEnd.'_object_id']) {
			// Input variables
			$objectID = $cable[$connectorEnd.'_object_id'];
			$objectFace = $cable[$connectorEnd.'_object_face'];
			$objectDepth = $cable[$connectorEnd.'_object_depth'];
			$objectPortID = $cable[$connectorEnd.'_object_port'];
			
			// Object variables
			$object = $this->objectArray[$objectID];
			$objectName = $object['name'];
			
			// Partition variables
			$partitionCompatibility = $this->compatibilityArray[$object['template_id']][$objectFace][$objectDepth];
			$templateType = $partitionCompatibility['templateType'];
			if($templateType == 'walljack') {
				$peerEntry = $this->peerArrayWalljackEntry[$objectID][$objectFace][$objectDepth][$objectPortID];
				$peerID = $peerEntry['id'];
				$peerFace = $peerEntry['face'];
				$peerDepth = $peerEntry['depth'];
				$objectPortID = $peerEntry['port'];
				$peer = $this->objectArray[$peerID];
				$peerTemplateID = $peer['template_id'];
				$partitionCompatibility = $this->compatibilityArray[$peerTemplateID][$peerFace][$peerDepth];
			}
			$partitionFunction = $partitionCompatibility['partitionFunction'];
			$portLayoutX = $partitionCompatibility['portLayoutX'];
			$portLayoutY = $partitionCompatibility['portLayoutY'];
			$portTotal = $portLayoutX * $portLayoutY;
			$portNameFormat = json_decode($partitionCompatibility['portNameFormat'],true);
			$portName = $this->generatePortName($portNameFormat, $objectPortID, $portTotal);
			
			// Port
			if($templateType == 'Insert') {
				if($partitionFunction == 'Endpoint') {
					$portString = $objectName.$portNumber;
				} else {
					$portString = '.&#8203;'.$objectName.'.&#8203;'.$portName;
				}
			} else {
				$portString = '.&#8203;'.$portName;
			}
			
			// Object
			if($templateType == 'Insert') {
				$parentID = $object['parent_id'];
				$object = $this->objectArray[$parentID];
			}
			$objectString = $object['name'];
			
			//Locations
			$locationString = '';
			$envNodeID = $object['env_tree_id'];
			$rootEnvNode = false;
			while(!$rootEnvNode) {
				$envNode = $this->envTreeArray[$envNodeID];
				$envNodeID = $envNode['parent'];
				$rootEnvNode = $envNodeID == '#' or !isset($this->envTreeArray[$envNodeID]) ? true : false;
				$locationString = $envNode['name'].'.&#8203;'.$locationString;
			}
			
			$flatPath = $locationString.$objectString.$portString;
		} else {
			$flatPath = 'None';
		}
		
		return $flatPath;
	}
	
	function getCableUnitOfLength($mediaTypeID){
		$mediaCategoryTypeID = $this->mediaTypeValueArray[$mediaTypeID]['category_type_id'];
		return $this->mediaCategoryTypeArray[$mediaCategoryTypeID]['unit_of_length'];
	}
	
	function clearInventoryTable($objID, $objFace, $objDepth, $objPort){
		if($inventory = $this->inventoryArray[$objID][$objFace][$objDepth][$objPort]) {
			foreach($inventory as $inventoryEntryIndex => $inventoryEntry) {
				$rowID = $inventoryEntry['rowID'];
				if($inventoryEntry['localEndID'] === 0 and $inventoryEntry['remoteEndID'] === 0) {
					// If this is an unmanaged connection, delete the entry
					$this->qls->SQL->delete('app_inventory', array('id' => array('=', $rowID)));
				} else {
					// If this is a managed connection, just clear the data
					$attrPrefix = $inventoryEntry['localAttrPrefix'];
					$set = array(
						$attrPrefix.'_object_id' => 0,
						$attrPrefix.'_object_face' => 0,
						$attrPrefix.'_object_depth' => 0,
						$attrPrefix.'_port_id' => 0
					);
					$this->qls->SQL->update('app_inventory', $set, array('id' => array('=', $rowID)));
					if(isset($this->inventoryArray[$inventoryEntry['id']][$inventoryEntry['face']][$inventoryEntry['depth']][$inventoryEntry['port']][$inventoryEntryIndex])) {
						$this->inventoryArray[$inventoryEntry['id']][$inventoryEntry['face']][$inventoryEntry['depth']][$inventoryEntry['port']][$inventoryEntryIndex]['id'] = 0;
						$this->inventoryArray[$inventoryEntry['id']][$inventoryEntry['face']][$inventoryEntry['depth']][$inventoryEntry['port']][$inventoryEntryIndex]['face'] = 0;
						$this->inventoryArray[$inventoryEntry['id']][$inventoryEntry['face']][$inventoryEntry['depth']][$inventoryEntry['port']][$inventoryEntryIndex]['depth'] = 0;
						$this->inventoryArray[$inventoryEntry['id']][$inventoryEntry['face']][$inventoryEntry['depth']][$inventoryEntry['port']][$inventoryEntryIndex]['port'] = 0;
					}
				}
			}
			unset($this->inventoryArray[$objID][$objFace][$objDepth][$objPort]);
		}
	}
	
	function clearPopulatedTable($objID, $objFace, $objDepth, $objPort){
		if($populatedPortEntry = $this->populatedPortArray[$objID][$objFace][$objDepth][$objPort]) {
			$rowID = $populatedPortEntry['rowID'];
			$this->qls->SQL->delete('app_populated_port', array('id' => array('=', $rowID)));
			unset($this->populatedPortArray[$objID][$objFace][$objDepth][$objPort]);
		}
	}
	
	// Not sure if this is useful :/
	function clearPeerTable($rowID){
		//$qls->SQL->delete('app_object_peer', array('id' => array('=', $rowID)));
		//unset($qls->App->peerArrayWalljack[$entry['selfID']]);
	}

	function calculateCableLength($mediaType, $length, $includeUnit=true) {

		// Collect details about the cable to help us calculate length
		$mediaCategoryTypeID = $this->mediaTypeValueArray[$mediaType]['category_type_id'];
		$mediaCategoryType = $this->mediaCategoryTypeArray[$mediaCategoryTypeID];
		$mediaCategoryTypeName = strtolower($mediaCategoryType['name']);
		
		if($length == 0) {
			$lengthString = 'Unk. Length';
			$includeUnit = false;
		} else if($mediaCategoryTypeName == 'copper') {
			// Convert to feet
			$lengthString = $this->convertToHighestHalfFeet($length);
		} else if($mediaCategoryTypeName == 'fiber') {
			// Convert to meters
			$lengthString = $this->convertToHighestHalfMeter($length);
		} else {
			$lengthString = $length;
		}
		
		if($includeUnit) {
			$lengthString = $lengthString.' '.$mediaCategoryType['unit_of_length'];
		}
		
		return $lengthString;
	}
	
	function convertToHighestHalfMeter($millimeter){
		$meters = $millimeter * 0.001;
		return round($meters * 2) / 2;
	}

	function convertToHighestHalfFeet($millimeter){
		$feet = $millimeter * 0.00328084;
		return round($feet * 2) / 2;
	}

	function convertFeetToMillimeters($cableLength){
		$meters = $cableLength * 0.3048;
		$millimeters = $meters * 1000;

		return $millimeters;
	}

	function convertMetersToMillimeters($cableLength){
		$millimeters = $cableLength * 1000;

		return $millimeters;
	}
	
	function logAction($function, $actionType, $actionString){
		
		if(strlen($actionString) > 200) {
			$actionString = substr($actionString, 0 , 200).'...';
		}
		
		$columns = array('date', 'function', 'action_type', 'user_id', 'action');
		$values = array(time(), $function, $actionType, $this->qls->user_info['id'], $actionString);
		$this->qls->SQL->insert('app_history', $columns, $values);
	}
	
	function buildPathFull2($pathArray){
		
		$pathOrientation = $this->qls->user_info['pathOrientation'];
		$trunkPairID = 0;
		
		$htmlPathFull = '';
		$htmlPathFull .= '<table>';
		
		// Cable Adjacent
		if($pathOrientation == 0) {
			
			foreach($pathArray as $pathArrayIndex => $connection) {
				
				// Set connectionPairID so connection can be drawn
				$connectionPairID = $pathArrayIndex;
				
				foreach($connection as $connectionIndex => $connectionSide) {
					
					if(count($connectionSide)) {
					
						// Set trunkPairID so trunk can be drawn
						if($connectionIndex == 1) {
							$trunkPairID++;
						}
						
						// Generate port name(s)
						$objName = '';
						$selected = false;
						$populated = false;
						foreach($connectionSide as $port) {
							
							$objID = $port['objID'];
							$objFace = $port['objFace'];
							$objDepth = $port['objDepth'];
							$objPort = $port['objPort'];
							$selected = ($port['selected'] == true) ? true : $selected;
							$portDiverges = $port['portDiverges'];
							$cableLength = $port['length'];
							$mediaTypeID = $port['mediaTypeID'];
							$connectorTypeID = $port['connectorTypeID'];
							$populated = (isset($this->populatedPortArray[$objID][$objFace][$objDepth][$objPort])) ? true : $populated;
							
							if(!$portDiverges) {
								$objName .= $this->generateObjectPortName($objID, $objFace, $objDepth, $objPort).'<br>';
							}
						}
						
						// Compile port name(s) in an object box
						$htmlPort = $this->wrapObject($objID, $objName, $selected, $trunkPairID);
						
						// Gather connector data
						if($connectorTypeID) {
							$portTypeName = $this->connectorTypeValueArray[$connectorTypeID]['name'];
						} else {
							$obj = $this->objectArray[$objID];
							$objTemplateID = $obj['template_id'];
							$objCompatibility = $this->compatibilityArray[$objTemplateID][$objFace][$objDepth];
							$portTypeID = $objCompatibility['portType'];
							$portTypeName = $this->portTypeValueArray[$portTypeID]['name'];
						}
						
						// Determine if this port is connected to another
						if($connectionIndex == 1) {
							$connected = (count($connection[0])) ? true : false;
						} else {
							$connected = (count($connection[1])) ? true : false;
						}
						
						if($connected or $populated) {
							// Compile connector div
							$htmlConnector = '<div title="'.$portTypeName.'" class="port '.$portTypeName.'" data-connection-pair-id='.$connectionPairID.'></div>';
							
							// Gather cable data
							if($mediaTypeID) {
								$mediaTypeName = $this->mediaTypeValueArray[$mediaTypeID]['name'];
							} else {
								$obj = $this->objectArray[$objID];
								$objTemplateID = $obj['template_id'];
								$objCompatibility = $this->compatibilityArray[$objTemplateID][$objFace][$objDepth];
								$mediaTypeID = $objCompatibility['mediaType'];
								$mediaTypeName = $this->mediaTypeValueArray[$mediaTypeID]['name'];
							}
							
							// Compile cable div
							$htmlCable = '<div style="width:100%;text-align:left;" title="'.$mediaTypeName.'" class="cable '.$mediaTypeName.' adjacent">'.$cableLength.'<br>'.$mediaTypeName.'</div>';
						} else {
							$htmlConnector = '';
							$htmlCable = '';
						}
						
						$htmlPathFull .= '<tr>';
						$htmlPathFull .= '<td>'.$htmlPort.'</td>';
						$htmlPathFull .= '<td>'.$htmlConnector.'</td>';
						if($connectionIndex == 0) {
							$htmlPathFull .= '<td rowspan="2">'.$htmlCable.'</td>';
						}
						$htmlPathFull .= '</tr>';
					}
				}
			}
			
		// Cable inline
		} else {
			
			foreach($pathArray as $pathArrayIndex => $connection) {
				
				// Set connectionPairID so connection can be drawn
				$connectionPairID = $pathArrayIndex;
				
				foreach($connection as $connectionIndex => $connectionSide) {
					
					if(count($connectionSide)) {
						// Set trunkPairID so trunk can be drawn
						if($connectionIndex == 1) {
							$trunkPairID++;
						}
						
						// Generate port name(s)
						$objName = '';
						$selected = false;
						$populated = false;
						foreach($connectionSide as $port) {
							
							$objID = $port['objID'];
							$objFace = $port['objFace'];
							$objDepth = $port['objDepth'];
							$objPort = $port['objPort'];
							$selected = ($port['selected'] == true) ? true : $selected;
							$portDiverges = $port['portDiverges'];
							$cableLength = $port['length'];
							$mediaTypeID = $port['mediaTypeID'];
							$connectorTypeID = $port['connectorTypeID'];
							$populated = (isset($this->populatedPortArray[$objID][$objFace][$objDepth][$objPort])) ? true : $populated;
							
							if(!$portDiverges) {
								$objName .= $this->generateObjectPortName($objID, $objFace, $objDepth, $objPort).'<br>';
							}
						}
						
						// Compile port name(s) in an object box
						$htmlPort = $this->wrapObject($objID, $objName, $selected, $trunkPairID);
						
						// Gather connector data
						if($connectorTypeID) {
							$portTypeName = $this->connectorTypeValueArray[$connectorTypeID]['name'];
						} else {
							$obj = $this->objectArray[$objID];
							$objTemplateID = $obj['template_id'];
							$objCompatibility = $this->compatibilityArray[$objTemplateID][$objFace][$objDepth];
							$portTypeID = $objCompatibility['portType'];
							$portTypeName = $this->portTypeValueArray[$portTypeID]['name'];
						}
						
						// Determine if this port is connected to another
						if($connectionIndex == 1) {
							$connected = (count($connection[0])) ? true : false;
						} else {
							$connected = (count($connection[1])) ? true : false;
						}
						
						if($connected or $populated) {
							// Compile connector div
							$htmlConnector = '<div title="'.$portTypeName.'" class="port '.$portTypeName.'" data-connection-pair-id='.$connectionPairID.'></div>';
							
							// Gather cable data
							if($mediaTypeID) {
								$mediaTypeName = $this->mediaTypeValueArray[$mediaTypeID]['name'];
							} else {
								$obj = $this->objectArray[$objID];
								$objTemplateID = $obj['template_id'];
								$objCompatibility = $this->compatibilityArray[$objTemplateID][$objFace][$objDepth];
								$mediaTypeID = $objCompatibility['mediaType'];
								$mediaTypeName = $this->mediaTypeValueArray[$mediaTypeID]['name'];
							}
							
							// Compile cable div
							$htmlCable = '<div style="width:100%;text-align:left;" title="'.$mediaTypeName.'" class="cable '.$mediaTypeName.' adjacent">'.$cableLength.'<br>'.$mediaTypeName.'</div>';
						} else {
							$htmlConnector = '';
							$htmlCable = '';
						}
						
						if($connectionIndex == 1) {
							$htmlPathFull .= '<tr>';
							$htmlPathFull .= '<td>'.$htmlConnector.'</td>';
							$htmlPathFull .= '</tr>';
							$htmlPathFull .= '<tr>';
							$htmlPathFull .= '<td>'.$htmlPort.'</td>';
							$htmlPathFull .= '</tr>';
						}
						
						
						if($connectionIndex == 0) {
							$htmlPathFull .= '<tr>';
							$htmlPathFull .= '<td>'.$htmlPort.'</td>';
							$htmlPathFull .= '</tr>';
							$htmlPathFull .= '<tr>';
							$htmlPathFull .= '<td>'.$htmlConnector.'</td>';
							$htmlPathFull .= '</tr>';
							$htmlPathFull .= '<tr><td>'.$htmlCable.'</td></tr>';
						}
					}
				}
			}
			
		}
		
		$htmlPathFull .= '<table>';
		return $htmlPathFull;
	}
	
	function buildPathFull($path, $connectorCode39){
		
		$htmlPathFull = '';
		$htmlPathFull .= '<table>';
		
		$tableArray = array(array());
		
		$pathOrientation = $this->qls->user_info['pathOrientation'];
		
		$connectionPairID = 1;
		$trunkPairID = 1;
		
		// Cable Adjacent
		if($pathOrientation == 0) {
			foreach($path as $objectIndex => $object) {
				$objType = $object['type'];
				
				switch($objType) {
					
					case 'connector':
						
						// Should connector be added?
						if(isset($path[$objectIndex+1])) {
							$addConnector = ($path[$objectIndex+1]['type'] != 'object') ? true : false;
						} else {
							$addConnector = true;
						}
						
						if($addConnector) {
							// Create blank "object" <td>
							if($objectIndex == 0 or (($objectIndex == count($path)-1) and ($path[$objectIndex-1]['type'] == 'cable'))) {
								array_push($tableArray[count($tableArray)-1], '<td>'.$this->wrapObject(0, 'None').'</td>');
							}
							
							// Generate and push connector HTML
							$connectorTypeID = $object['data']['connectorType'];
							$connectorTypeName = ($connectorTypeID != 0) ? $this->portTypeValueArray[$connectorTypeID]['name'] : 'Unk';
							$htmlString = '<td><div title="'.$connectorTypeName.'" class="port '.$connectorTypeName.'" data-connection-pair-id='.$connectionPairID.'></div></td>';
							array_push($tableArray[count($tableArray)-1], $htmlString);
							
							if($objectIndex == count($path)-1) {
								array_push($tableArray[count($tableArray)-1], '<td></td>');
							}
						}
						
						break;
						
					case 'cable':
					
						$trunkPairID++;
					
						$cableTypeID = $object['data']['mediaTypeID'];
						if($cableTypeID != 0) {
							$cableTypeName = $mediaTypeClass = $this->mediaTypeValueArray[$cableTypeID]['name'];
						} else {
							$cableTypeName = 'Unk. Media Type';
							$mediaTypeClass = 'Unk';
						}
						$cableLength = $object['data']['length'];
						
						$htmlString = '<div style="width:100%;text-align:left;" title="'.$cableTypeName.'" class="cable '.$mediaTypeClass.' adjacent">';
						$htmlString .= $cableLength.'<br>'.$cableTypeName;
						$htmlString .= '</div>';
						
						// Wrap in <td> and add to row array
						$htmlString = '<td rowspan="2">'.$htmlString.'</td>';
						array_push($tableArray[count($tableArray)-1], $htmlString);
						
						// Add new row array in table array
						array_push($tableArray, array());
						
						break;
						
					case 'object':
					
						$objName = '';
						foreach($object['data'] as $item) {
							$objID = $item['id'];
							$objFace = $item['face'];
							$objDepth = $item['depth'];
							$objPort = $item['port'];
							$selected = $item['selected'];
							$objName .= $this->generateObjectPortName($objID, $objFace, $objDepth, $objPort).'<br>';
						}
						
						$objBox = $this->wrapObject($objID, $objName, $selected, $trunkPairID);
							
						// Wrap in <td> and add to row array
						$htmlString = '<td>'.$objBox.'</td>';
						array_push($tableArray[count($tableArray)-1], $htmlString);
						
						if($path[$objectIndex+1]['type'] == 'trunk' or !isset($path[$objectIndex+1])) {
							if(isset($path[$objectIndex-1])) {
								$connectorTypeID = $path[$objectIndex-1]['data']['connectorType'];
								$connectorTypeName = ($connectorTypeID != 0) ? $this->portTypeValueArray[$connectorTypeID]['name'] : 'Unk';
								$htmlString = '<td><div title="'.$connectorTypeName.'" class="port '.$connectorTypeName.'" data-connection-pair-id='.$connectionPairID.'></div></td>';
								array_push($tableArray[count($tableArray)-1], $htmlString);
								
								array_push($tableArray[count($tableArray)-1], '<td></td>');
							} else {
								array_push($tableArray[count($tableArray)-1], '<td></td>');
								array_push($tableArray[count($tableArray)-1], '<td></td>');
							}
							
							// Add new row array in table array
							array_push($tableArray, array());
						}
						
						break;
						
					case 'trunk':
					
						$connectionPairID++;
						
						$htmlString = '<div title="Trunk" class="trunk adjacent">';
						
						// Wrap in <td> and add to row array
						$htmlString = '<td>'.$htmlString.'</td><td></td><td></td>';
						array_push($tableArray[count($tableArray)-1], $htmlString);
						
						// Add new row array in table array
						array_push($tableArray, array());
						
						break;
				}
			}
			
		// Cable inline
		} else {
			
			foreach($path as $objectIndex => $object) {
				$objType = $object['type'];
				
				switch($objType) {
					
					case 'object':
						if($objectIndex) {
							if($path[$objectIndex+1]['type'] == 'trunk') {
								$bottomTableTag = '</td>';
							} else {
								$bottomTableTag = '</td></tr>';
							}
						} else {
							$bottomTableTag = '</td>';
						}
						$topTableTag = '<tr><td>';
						$htmlPathFull .= $topTableTag;
						$objName = '';
						foreach($object['data'] as $item) {
							$objID = $item['id'];
							$objFace = $item['face'];
							$objDepth = $item['depth'];
							$objPort = $item['port'];
							$selected = $item['selected'];
							$objName .= $this->generateObjectPortName($objID, $objFace, $objDepth, $objPort).'<br>';
						}
						$objBox = $this->wrapObject($objID, $objName, $selected, $trunkPairID);
						
						$htmlPathFull .= $objBox;
						$htmlPathFull .= $bottomTableTag;
						break;
					
					case 'connector':
						$htmlPathFull .= '<tr><td>';
						$connectorTypeID = $object['data']['connectorType'];
						
						if($connectorTypeID != 0) {
							$connectorTypeName = $this->connectorTypeValueArray[$connectorTypeID]['name'];
							$code39 = $object['data']['code39'];
							$connectorClass = $code39 != 0 ? 'cableConnector cursorPointer' : '';
							
							$htmlPathFull .= '<div title="'.$connectorTypeName.'" class="port '.$connectorTypeName.' '.$connectorClass.'" data-code39="'.$code39.'" data-connection-pair-id='.$connectionPairID.'></div>';
							
						} else {
							$connectorTypeName = 'Unk';
							
							$htmlPathFull .= '<div title="'.$connectorTypeName.'" class="port '.$connectorTypeName.'" data-connection-pair-id='.$connectionPairID.'>';
							
						}
						$htmlPathFull .= '</td><td></td></tr>';
						break;
						
					case 'cable':
					
						$trunkPairID++;
					
						$htmlPathFull .= '<tr><td>';
						$cableTypeID = $object['data']['mediaTypeID'];
						if($cableTypeID != 0) {
							$cableTypeName = $mediaTypeClass = $this->mediaTypeValueArray[$cableTypeID]['name'];
						} else {
							$cableTypeName = 'Unk. Media Type';
							$mediaTypeClass = 'Unk';
						}
						$cableLength = $object['data']['length'];
						
						$htmlPathFull .= '<div style="width:100%;text-align:left;" title="'.$cableTypeName.'" class="cable '.$mediaTypeClass.' stacked">';
						$htmlPathFull .= $cableLength.'<br>'.$cableTypeName;
						$htmlPathFull .= '</div>';
						$htmlPathFull .= '</td><td></td></tr>';
						break;
						
					case 'trunk':
					
						$connectionPairID++;
					
						$htmlPathFull .= '<td rowspan="2">';
						$htmlPathFull .= '<div title="Trunk" class="trunk stacked">';
						$htmlPathFull .= '</td>';
						break;
				}
			}
		}
		$htmlPathFull .= '</table>';
		
		if($pathOrientation == 0) {
			$htmlString = '<table>';
			foreach($tableArray as $tableRow) {
				$htmlString .= '<tr>'.implode($tableRow).'</tr>';
			}
			$htmlString .= '</table>';
			return $htmlString;
		} else {
			return $htmlPathFull;
		}
		
	}
	
	function buildObject($obj){
		$objectID = $obj['id'];
		$objectElements = $obj['obj'];
		$function = $obj['function'];
		$categoryID = $obj['categoryID'];
		$objSelected = $obj['selected'];
		$return = '';
		$return .= '<td>';
			
			if ($objectID != 0) {
				$elementArray = array();
				foreach($objectElements as $elementIndex => $element){
					array_push($elementArray, $element);
				}
				$objName = $objSelected ? '<i class="fa fa-map-marker" title="Selected Object"></i>&nbsp;' : '';
				$objName .= implode('.', $elementArray);
			} else {
				$objName = 'None';
			}
			
			$return .= $this->wrapObject($objectID, $objName);
			
		$return .= '</td>';
		return $return;
	}
	
	function buildCable($topCode39, $btmCode39, $connectorCode39, $length){
		
		$return = '';
		$scanned = $topCode39 == $connectorCode39 ? true : false;
		$return .= $this->displayArrow('top', $scanned, $topCode39);
		$return .= $length;
		$scanned = $btmCode39 == $connectorCode39 ? true : false;
		$return .= $this->displayArrow('btm', $scanned, $btmCode39);
		
		return $return;
	}
	
	function displayArrow($orientation, $scanned, $code39){
		$fill = $scanned ? '#039cfd' : '#ffffff';
		$top = '<path stroke="#000000" fill="'.$fill.'" id="'.$code39.'" transform="rotate(-180 10,10)" d="m12.34666,15.4034l0.12924,-1.39058l-1.52092,-0.242c-3.85063,-0.61265 -7.62511,-3.21056 -9.7267,-6.69472c-0.37705,-0.62509 -0.62941,-1.22733 -0.56081,-1.33833c0.15736,-0.25462 3.99179,-2.28172 4.31605,-2.28172c0.13228,0 0.45004,0.37281 0.70613,0.82847c1.09221,1.9433 3.91879,3.97018 5.9089,4.2371l0.80686,0.10823l-0.13873,-1.2018c-0.14402,-1.24763 -0.10351,-1.50961 0.23337,-1.50961c0.21542,0 6.64622,4.79111 6.83006,5.08858c0.13947,0.22565 -0.74504,1.06278 -3.91187,3.70233c-1.37559,1.14654 -2.65852,2.08463 -2.85095,2.08463c-0.308,0 -0.33441,-0.16643 -0.22064,-1.39058l0,0l0,0l0,-0.00001z" stroke-linecap="null" stroke-linejoin="null" stroke-dasharray="null"/>';
		$btm = '<path stroke="#000000" fill="'.$fill.'" id="'.$code39.'" transform="rotate(-180 10,10)" stroke-dasharray="null" stroke-linejoin="null" stroke-linecap="null" d="m12.34666,4.88458l0.12924,1.38058l-1.52092,0.24026c-3.85063,0.60825 -7.62511,3.18748 -9.7267,6.64659c-0.37705,0.6206 -0.62941,1.21851 -0.56081,1.32871c0.15736,0.25279 3.99179,2.26532 4.31605,2.26532c0.13228,0 0.45004,-0.37013 0.70613,-0.82251c1.09221,-1.92933 3.91879,-3.94164 5.9089,-4.20664l0.80686,-0.10745l-0.13873,1.19316c-0.14402,1.23866 -0.10351,1.49876 0.23337,1.49876c0.21542,0 6.64622,-4.75667 6.83006,-5.052c0.13947,-0.22403 -0.74504,-1.05514 -3.91187,-3.67571c-1.37559,-1.1383 -2.65852,-2.06964 -2.85095,-2.06964c-0.308,0 -0.33441,0.16523 -0.22064,1.38058l0,0l0,0l0,0.00001l0.00001,-0.00001z"/>';
		
		$arrow = '<div class="cableArrow" data-code39="'.$code39.'" title="'.$code39.'">';
		$arrow .= '<svg width="20" height="20" style="display:block;">';
		$arrow .= '<g>';
		$arrow .= $orientation == 'top' ? $top : $btm;
		$arrow .= '</g>';
		$arrow .= '</svg>';
		$arrow .= '</div>';
		
		return $arrow;
	}
	
	function displayTrunk(){
		$trunk = '';
		$trunk .= '<td rowspan="2" style="vertical-align:middle;">';
		$trunk .= '<svg width="20" height="40">';
		$trunk .= '<g>';
		$trunk .= '<path stroke="#000000" fill="#ffffff" transform="rotate(-90 10,20)" d="m-6.92393,20.00586l9.84279,-8.53669l0,4.26834l14.26478,0l0,-4.26834l9.84279,8.53669l-9.84279,8.53665l0,-4.26832l-14.26478,0l0,4.26832l-9.84279,-8.53665z" stroke-linecap="null" stroke-linejoin="null" stroke-dasharray="null" stroke-width="null"/>';
		$trunk .= '</g>';
		$trunk .= '</svg>';
		$trunk .= '</td>';
		return $trunk;
	}
	
	function gatherEntitlementData(){
		
		$this->entitlementArray = array();
		$query = $this->qls->SQL->select('*', 'app_organization_data', array('id' => array('=', 1)));
		
		//while($row = $this->qls->SQL->fetch_assoc($query)) {
			$row = $this->qls->SQL->fetch_assoc($query);
			$entitlementData = json_decode($row['entitlement_data'], true);
			$this->entitlementArray['id'] = $row['entitlement_id'];
			$this->entitlementArray['lastChecked'] = $row['entitlement_last_checked'];
			$this->entitlementArray['lastCheckedFormatted'] = $this->formatTime($row['entitlement_last_checked']);
			$this->entitlementArray['expiration'] = $row['entitlement_expiration'];
			$this->entitlementArray['expirationFormatted'] = $row['entitlement_expiration'] > 0 ? $this->formatTime($row['entitlement_expiration']) : 'N/A';
			$this->entitlementArray['status'] = $row['entitlement_comment'];
			$this->entitlementArray['data'] = array();
			
			foreach($entitlementData as $item => $value) {
				$workingArray = array();
				if($item == 'cabinetCount') {
					
					// Find number used
					$query = $this->qls->SQL->select('id', 'app_env_tree', array('type' => array('=', 'cabinet')));
					$cabNum = $this->qls->SQL->num_rows($query);
					
					// Store attribute data
					$workingArray['attribute'] = $item;
					$workingArray['count'] = $value;
					$workingArray['friendlyName'] = 'Cabinets';
					$workingArray['used'] = $cabNum;
					
				} else if($item == 'objectCount') {
					
					// Find number used
					$query = $this->qls->SQL->select('id', 'app_object');
					$objNum = $this->qls->SQL->num_rows($query);
					
					// Store attribute data
					$workingArray['attribute'] = $item;
					$workingArray['count'] = $value;
					$workingArray['friendlyName'] = 'Objects';
					$workingArray['used'] = $objNum;
					
				} else if($item == 'connectionCount') {
					
					// Find number used
					$query = $this->qls->SQL->select('id', 'app_inventory', array('a_object_id' => array('>', 0), 'AND', 'b_object_id' => array('>', 0)));
					$conNum = $this->qls->SQL->num_rows($query);
					
					// Store attribute data
					$workingArray['attribute'] = $item;
					$workingArray['count'] = $value;
					$workingArray['friendlyName'] = 'Connections';
					$workingArray['used'] = $conNum;
					
				} else if($item == 'userCount') {
					
					// Find number used
					$query = $this->qls->SQL->select('id', 'users');
					$userNum = $this->qls->SQL->num_rows($query);
					
					// Store attribute data
					$workingArray['attribute'] = $item;
					$workingArray['count'] = $value;
					$workingArray['friendlyName'] = 'Users';
					$workingArray['used'] = $userNum;
					
				}
				$this->entitlementArray['data'][$item] = $workingArray;
			}
			
		//}
		
		return;
	}
	
	function updateEntitlementData($entitlementID=false){
		
		$entitlementID = ($entitlementID) ? $entitlementID : $this->entitlementArray['id'];
		$appID = $this->qls->org_info['app_id'];
		
		// POST Request
		$data = array(
			'entitlementID' => $entitlementID,
			'appID' => $appID
		);
		$dataJSON = json_encode($data);
		$POSTData = array('data' => $dataJSON);
		
		$ch = curl_init('https://patchcablemgr.com/public/process_entitlement.php?version='.PCM_VERSION);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $POSTData);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, "/etc/ssl/certs/");
		
		// Submit the POST request
		$responseJSON = curl_exec($ch);
		
		//Check for request errors.
		if(curl_errno($ch)) {
			$this->qls->SQL->update('app_organization_data', array('entitlement_last_checked' => time()), array('id' => array('=', 1)));
		} else {
			
			if($response = json_decode($responseJSON, true)) {
				$updateValues = array(
					'entitlement_last_checked' => time(),
					'entitlement_expiration' => $response['expiration'],
					'entitlement_data' => json_encode($response['data']),
					'entitlement_comment' => $response['comment']
				);
				$this->qls->SQL->update('app_organization_data', $updateValues, array('id' => array('=', 1)));
			}
		}
		
		// Close cURL session handle
		curl_close($ch);
		
		return;
	}
	
	function cancelEntitlement(){
		
		$entitlementID = $this->entitlementArray['id'];
		
		// POST Request
		$data = array(
			'action' => 'cancel',
			'entitlementID' => $entitlementID
		);
		$dataJSON = json_encode($data);
		$POSTData = array('data' => $dataJSON);
		
		$ch = curl_init('https://patchcablemgr.com/public/process_subscription.php');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $POSTData);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, "/etc/ssl/certs/");
		
		// Submit the POST request
		$responseJSON = curl_exec($ch);
		
		$this->qls->SQL->update('app_organization_data', array('entitlement_last_checked' => time()), array('id' => array('=', 1)));
		
		//Check for request errors.
		if(!curl_errno($ch)) {
			if($response = json_decode($responseJSON, true)) {
				if(!count($response['error'])) {
					$this->qls->SQL->update('app_organization_data', array('entitlement_comment' => 'canceled'), array('id' => array('=', 1)));
				}
			}
		}
		
		// Close cURL session handle
		curl_close($ch);
		
		return;
	}
	
	function checkEntitlement($attribute, $count){
		switch($attribute) {
			case 'cabinet':
				$entitlementCount = $this->entitlementArray['data']['cabinetCount']['count'];
				if($entitlementCount != 0) {
					return ($count > $entitlementCount) ? false : true;
				}
				
			case 'object':
				$entitlementCount = $this->entitlementArray['data']['objectCount']['count'];
				if($entitlementCount != 0) {
					return ($count > $entitlementCount) ? false : true;
				}
				
			case 'connection':
				$entitlementCount = $this->entitlementArray['data']['connectionCount']['count'];
				if($entitlementCount != 0) {
					return ($count > $entitlementCount) ? false : true;
				}
				
			case 'user':
				$entitlementCount = $this->entitlementArray['data']['userCount']['count'];
				if($entitlementCount != 0) {
					return ($count > $entitlementCount) ? false : true;
				}
		}
		
		return true;
	}
	
	function formatTime($unixTimeStamp) {
		$dt = new DateTime("@$unixTimeStamp", new DateTimeZone('UTC'));
		$dt->setTimezone(new DateTimeZone($this->qls->user_info['timezone']));
		$dateFormatted = $dt->format('d-M-Y H:i:s');
		return $dateFormatted;
	}
	
	function buildStandard($data, $isCombinedTemplate, $objID=false, $objFace=false, $cabinetView=false, &$depthCounter=0){
		$html = '';
		$encInsert = false;
		foreach($data as $element){
			
			$partitionType = $element['partitionType'];
			$html .= $this->generatePartition($element, $objID, $objFace, $depthCounter);
			
			switch($partitionType){
				case 'Generic':
				
					if(isset($element['children'])){
						$depthCounter++;
						$html .= $this->buildStandard($element['children'], $isCombinedTemplate, $objID, $objFace, $cabinetView, $depthCounter);
					}
					break;
					
				case 'Connectable':
				
					if($cabinetView === false) {
						$html .= $this->buildConnectable($element, $objID, $objFace, $depthCounter);
					}
					break;
					
				case 'Enclosure':
				
					$valueX = $element['valueX'];
					$valueY = $element['valueY'];
					
					$html .= $this->buildEnclosure($valueX, $valueY, $isCombinedTemplate, $objID, $objFace, $cabinetView, $depthCounter);
					break;
			}
			$html .= '</div>';
			$depthCounter++;
		}
		return $html;
	}

	function buildConnectable($element, $objID, $objFace, $objDepth){
		
		$objID = $objID ? $objID : 0;
		$objFace = $objFace ? $objFace : 0;
		
		$portX = $element['valueX'];
		$portY = $element['valueY'];
		$portTypeID = $element['portType'];
		$portOrientationID = $element['portOrientation'];
		$portNameFormat = $element['portNameFormat'];
		
		$portTotal = $portX * $portY;
		$html = '<div class="border-black" style="display:flex;height:100%;flex-direction:column;flex:1;">';
			for ($y = 0; $y < $portY; $y++){
				$html .= '<div class="tableRow">';
				for ($x = 0; $x < $portX; $x++){
					
					$html .= '<div class="tableCol">';
					
					$portIndex = $this->getPortIndex($portOrientationID, $x, $y, $portX, $portY);
					
					// Generate attributes
					$attrAssocArray = array(
						'data-port-index' => $portIndex
					);
					
					// Generate CSS classes
					$classArray = array(
						'port',
						$this->portTypeArray[$portTypeID]['name']
					);
					if($objID) {
						
						// GlobalID
						$globalID = 'port-4-'.$objID.'-'.$objFace.'-'.$objDepth.'-'.$portIndex;
						
						// Class - populated
						if(isset($this->populatedPortArray[$objID][$objFace][$objDepth][$portIndex])) {
							array_push($classArray, 'populated');
							
						// Class - connected
						} else if(isset($this->inventoryArray[$objID][$objFace][$objDepth][$portIndex])) {
							array_push($classArray, 'populated');
						}
						
						if(isset($this->peerArray[$objID][$objFace][$objDepth])) {
							if($this->peerArray[$objID][$objFace][$objDepth]['floorplanPeer'] == 0) {
								array_push($classArray, 'endpointTrunked');
							} else {
								foreach($this->peerArray[$objID][$objFace][$objDepth]['peerArray'] as $peerID) {
									foreach($peerID as $peerFace) {
										foreach($peerFace as $peerDepth) {
											foreach($peerDepth as $peerEntry) {
												if($portIndex == $peerEntry[0]) {
													array_push($classArray, 'endpointTrunked');
												}
											}
										}
									}
								}
							}
						}
						
						// Attr - code39
						$code39 = 0;
						$connectionArray = array();
						if(isset($this->inventoryArray[$objID][$objFace][$objDepth][$portIndex])) {
							$connectionArray = array();
							foreach($this->inventoryArray[$objID][$objFace][$objDepth][$portIndex] as $connection) {
								$inventoryID = $connection['localEndID'];
								$code39 = $this->inventoryByIDArray[$inventoryID]['localEndCode39'];
								
								// Connected Object GlobalID
								$connectedGlobalID = 'port-4-'.$connection['id'].'-'.$connection['face'].'-'.$connection['depth'].'-'.$connection['port'];
								array_push($connectionArray, $connectedGlobalID);
							}
						}
						$attrAssocArray['data-code39'] = $code39;
						$attrAssocArray['data-connected-global-id'] = base64_encode(json_encode($connectionArray));
						
						// Attr - title
						$attrAssocArray['title'] = $this->generatePortName($portNameFormat, $portIndex, $portTotal);
					}
					
					$attrArray = array();
					foreach($attrAssocArray as $attrName => $attrValue) {
						array_push($attrArray, $attrName.'="'.$attrValue.'"');
					}
					$attrString = implode(' ', $attrArray);
					$classString = implode(' ', $classArray);
					
					$html .= '<div id="'.$globalID.'" class="'.$classString.'" '.$attrString.'></div>';
					$html .= '</div>';
				}
				$html .= "</div>";
			}
		$html .= '</div>';
		return $html;
	}
	
	function buildEnclosure($encX, $encY, $isCombinedTemplate, $objID=false, $objFace=false, $cabinetView=false, $depthCounter=false){
		
		$html = '<div class="enclosure" style="display:flex;flex:1;height:100%;" data-enc-obj-face="'.$objFace.'" data-enc-obj-depth="'.$depthCounter.'" data-enc-layout-x="'.$encX.'" data-enc-layout-y="'.$encY.'">';
		for ($y = 0; $y < $encY; $y++){
			
			$rowBorderClass = ($y == 0) ? '' : 'borderTop';
			$html .= '<div class="'.$rowBorderClass.' tableRow">';
				for ($x = 0; $x < $encX; $x++){
					
					$colBorderClass = ($x == ($encX-1)) ? '' : 'borderRight';
					$html .= '<div class="'.$colBorderClass.' tableCol enclosureTable insertDroppable" data-enc-x="'.$x.'" data-enc-y="'.$y.'">';
					
					// Check if insert is installed in enclosure slot
					if($objID !== false and $objFace !== false and $depthCounter !== false) {
						
						if(isset($this->insertAddressArray[$objID][$objFace][$depthCounter][$x][$y])) {
							$insert = $this->insertAddressArray[$objID][$objFace][$depthCounter][$x][$y];
							$insertObjID = $insert['id'];
							$insertTemplateID = $insert['template_id'];
							$insertTemplate = $this->templateArray[$insertTemplateID];
							$insertPartitionDataJSON = $insertTemplate['templatePartitionData'];
							$insertPartitionData = json_decode($insertPartitionDataJSON, true);
							
							$objClassArray = array(
								'rackObj',
								'insertDraggable'
							);
							$categoryData = false;
							$html .= $this->generateObjContainer($insertTemplate, 0, $objClassArray, $isCombinedTemplate, $insertObjID, $categoryData, $cabinetView);
							$objFace = 0;
							$html .= $this->buildStandard($insertPartitionData[$objFace], $isCombinedTemplate, $insertObjID, $objFace, $cabinetView);
							$html .= '</div>';
						}
					} else if($isCombinedTemplate) {
						foreach($isCombinedTemplate as $templateData) {
							
							$insertTemplateID = $templateData['templateID'];
							$parentFace = $templateData['parentFace'];
							$parentDepth = $templateData['parentDepth'];
							$combinedEncX = $templateData['encX'];
							$combinedEncY = $templateData['encY'];
							
							if($parentFace == $objFace and $parentDepth == $depthCounter and $combinedEncX == $x and $combinedEncY == $y) {
								$insertTemplate = $this->templateArray[$insertTemplateID];
								$insertPartitionDataJSON = $insertTemplate['templatePartitionData'];
								$insertPartitionData = json_decode($insertPartitionDataJSON, true);
								
								$objClassArray = array(
									'stockObj'
								);
								$categoryData = false;
								$html .= $this->generateObjContainer($insertTemplate, 0, $objClassArray, $isCombinedTemplate, $insertObjID, $categoryData, $cabinetView);
								$objFace = 0;
								$html .= $this->buildStandard($insertPartitionData[$objFace], $isCombinedTemplate, $insertObjID, $objFace, $cabinetView);
								$html .= '</div>';
							}
						}
					}
					$html .= '</div>';
				}
			$html .= '</div>';
		}
		$html .= '</div>';
		return $html;
	}

	function getPortIndex($orientation, $x, $y, $portX, $portY){
		$portTotal = $portX * $portY;
		if($orientation == 1) {
			$portIndex = ($y * $portX) + $x;
		} else if($orientation == 2) {
			$portIndex = ($x * $portY) + $y;
		} else if($orientation == 3) {
			$portIndex = ($y * $portX) + (($portX - $x) - 1);
		} else if($orientation == 4) {
			$portIndex = ($portTotal - ($y * $portX)) - ($portX - $x);
		} else if($orientation == 5) {
			$portIndex = ($portY * $x) + ($portY - ($y+1));
		}
		return $portIndex;
	}

	function generatePartition($partition, $objID, $objFace, $depth){
		$objID = $objID ? $objID : 0;
		$objFace = $objFace ? $objFace : 0;
		$globalID = 'part-3-'.$objID.'-'.$objFace.'-'.$depth.'-0';
		
		$objAttrArray = array();
		
		$partitionType = $partition['partitionType'];
		$flexDirection = $partition['direction'];
		$flex = $partition['flex'];
		$hUnits = $partition['hUnits'];
		$vUnits = $partition['vUnits'];
		
		$objAttrArray['data-direction'] = $flexDirection;
		$objAttrArray['data-partition-type'] = $partitionType;
		$objAttrArray['data-depth'] = $depth;
		$objAttrArray['data-h-units'] = $hUnits;
		$objAttrArray['data-v-units'] = $vUnits;
		
		$classArray = array('partition');
		
		if($partitionType == 'Generic') {
			
			if($depth == 0) {
				array_push($classArray, 'selectable');
			}
			
		} else if($partitionType == 'Connectable') {
			
			// Collect Connectable partition data
			$valueX = $partition['valueX'];
			$valueY = $partition['valueY'];
			$portOrientationID = $partition['portOrientation'];
			$portNameFormat = $partition['portNameFormat'];
			$portNameFormatString = json_encode($portNameFormat);
			$portTypeID = $partition['portType'];
			$mediaTypeID = $partition['mediaType'];
			
			// Add Connectable partition data to attribute array
			$objAttrArray['data-port-orientation'] = $portOrientationID;
			$objAttrArray['data-port-type'] = $portTypeID;
			$objAttrArray['data-media-type'] = $mediaTypeID;
			$objAttrArray['data-value-x'] = $valueX;
			$objAttrArray['data-value-y'] = $valueY;
			$objAttrArray['data-port-name-format'] = '\''.$portNameFormatString.'\'';
			
			// Find trunk peer if it exists
			if(isset($this->peerArray[$objID][$objFace][$depth])) {
				$peer = $this->peerArray[$objID][$objFace][$depth];
				$peerID = $peer['peerID'];
				$peerFace = $peer['peerFace'];
				$peerDepth = $peer['peerDepth'];
				
				$peerGlobalID = 'part-3-'.$peerID.'-'.$peerFace.'-'.$peerDepth.'-0';
			} else {
				$peerGlobalID = 'none';
			}
			
			$objAttrArray['data-peer-global-id'] = $peerGlobalID;
			
			array_push($classArray, 'selectable');
			
		} else if($partitionType == 'Enclosure') {
			
			// Collect Enclosure partition data
			$valueX = $partition['valueX'];
			$valueY = $partition['valueY'];
			$encTolerance = $partition['encTolerance'];
			
			// Add Enclosure partition data to attribute array
			$objAttrArray['data-value-x'] = $valueX;
			$objAttrArray['data-value-y'] = $valueY;
			$objAttrArray['data-enc-tolerance'] = '"'.$encTolerance.'"';
			
			array_push($classArray, 'selectable');
		}
		
		if($depth == 0) {
			$flex = $flexDirection == 'column' ? $hUnits/10 : $vUnits*0.5;
			$flex = 1;
			array_push($classArray, 'flex-container-parent');
		} else {
			array_push($classArray, 'flex-container');
		}
		
		// Generate data attribute string
		$objAttrWorkingArray = array();
		foreach($objAttrArray as $attr => $value) {
			array_push($objAttrWorkingArray, $attr.'='.$value);
		}
		
		$objAttr = implode(' ', $objAttrWorkingArray);
		$objClass = implode(' ', $classArray);
		
		$html = '<div id="'.$globalID.'" class="'.$objClass.'" style="flex:'.$flex.'; flex-direction:'.$flexDirection.';" '.$objAttr.'>';
		
		return $html;
	}

	function generateObjContainer($template, $face, $objClassArray, $isCombinedTemplate, $objID=false, $categoryData=false, $cabinetView=false){
		$templateID = $template['id'];
		$templateName = $template['templateName'];
		$templateType = $template['templateType'];
		$templateRUSize = $template['templateRUSize'];
		$templateFunction = $template['templateFunction'];
		$templateFrontImage = $template['frontImage'];
		$templateRearImage = $template['rearImage'];
		$categoryID = $template['templateCategory_id'];
		$categoryName = ($categoryData !== false) ? $categoryData['name'] : $this->categoryArray[$categoryID]['name'];
		$parentHUnits = $template['templateHUnits'];
		$parentVUnits = $template['templateVUnits'];
		$parentEncLayoutX = $template['templateEncLayoutX'];
		$parentEncLayoutY = $template['templateEncLayoutY'];
		$isCombinedTemplate = ($isCombinedTemplate) ? 'yes' : 'no';
		
		// Object data
		$objAttrArray = array();
		$objAttrArray['data-template-type'] = '"'.$templateType.'"';
		$objAttrArray['data-object-face'] = $face;
		$objAttrArray['data-template-id'] = $templateID;
		$objAttrArray['data-template-name'] = $templateName;
		$objAttrArray['data-template-combined'] = $isCombinedTemplate;
		$objAttrArray['data-ru-size'] = $templateRUSize;
		$objAttrArray['data-template-function'] = '"'.$templateFunction.'"';
		$objAttrArray['data-template-front-image'] = '"'.$templateFrontImage.'"';
		$objAttrArray['data-template-rear-image'] = '"'.$templateRearImage.'"';
		$objAttrArray['data-template-category-id'] = $categoryID;
		$objAttrArray['data-template-category-name'] = $categoryName;
		
		// Object ID
		if($objID) {
			$objAttrArray['data-template-object-id'] = $objID;
			$objAttrArray['data-template-object-name'] = $this->qls->App->objectArray[$objID]['name'];
			$globalID = 'obj-2-'.$objID.'-0-0-0';
		} else {
			$globalID = 'obj-2-'.$templateID.'-0-0-0';
		}
		
		// Mount config
		if($templateType == 'Standard') {
			$templateMountConfig = $template['templateMountConfig'];
			$objAttrArray['data-object-mount-config'] = $templateMountConfig;
		}
		
		if($templateType == 'Insert') {
			$parentHUnits = $template['templateHUnits'];
			$parentVUnits = $template['templateVUnits'];
			$parentEncLayoutX = $template['templateEncLayoutX'];
			$parentEncLayoutY = $template['templateEncLayoutY'];
			$nestedParentHUnits = $template['nestedParentHUnits'];
			$nestedParentVUnits = $template['nestedParentVUnits'];
			$nestedParentEncLayoutX = $template['nestedParentEncLayoutX'];
			$nestedParentEncLayoutY = $template['nestedParentEncLayoutY'];
			$objAttrArray['data-h-units'] = $parentHUnits;
			$objAttrArray['data-v-units'] = $parentVUnits;
			$objAttrArray['data-parent-h-units'] = $parentHUnits;
			$objAttrArray['data-parent-v-units'] = $parentVUnits;
			$objAttrArray['data-parent-enc-layout-x'] = $parentEncLayoutX;
			$objAttrArray['data-parent-enc-layout-y'] = $parentEncLayoutY;
			$objAttrArray['data-nested-insert'] = ($nestedParentHUnits and $nestedParentVUnits) ? 1 : 0;
			$objAttrArray['data-nested-parent-h-units'] = ($nestedParentHUnits) ? $nestedParentHUnits : 0;
			$objAttrArray['data-nested-parent-v-units'] = ($nestedParentVUnits) ? $nestedParentVUnits : 0;
			$objAttrArray['data-nested-parent-enc-layout-x'] = ($nestedParentEncLayoutX) ? $nestedParentEncLayoutX : 0;
			$objAttrArray['data-nested-parent-enc-layout-y'] = ($nestedParentEncLayoutY) ? $nestedParentEncLayoutY : 0;
			
			array_push($objClassArray, 'insert');
		}

		// Generate data attribute string
		$objAttrWorkingArray = array();
		foreach($objAttrArray as $attr => $value) {
			array_push($objAttrWorkingArray, $attr.'='.$value);
		}
		
		$objStyleArray = array(
			'display:flex;',
			'flex:1;'
		);
		
		if($categoryData !== false) {
			$colorCode = $categoryData['color'];
			array_push($objStyleArray, 'background-color:'.$colorCode.';');
		} else {
			array_push($objClassArray, 'category'.$categoryName);
		}
		
		// Assess cabinet view type
		if($cabinetView !== false) {
			if($cabinetView == 'visual') {
				$templateImgAttr = $face == 0 ? 'frontImage' : 'rearImage';
				if($template[$templateImgAttr] !== null) {
					$templateImgPath = '/images/templateImages/'.$template[$templateImgAttr];
					array_push($objStyleArray, 'background-image: url('.$templateImgPath.');');
					array_push($objStyleArray, 'background-size: 100% 100%;');
				}
			}
		}
		
		$dataAttr = implode(' ', $objAttrWorkingArray);
		$objClass = implode(' ', $objClassArray);
		$objStyle = implode('', $objStyleArray);
		
		$html = '<div id="'.$globalID.'" style="'.$objStyle.'" class="'.$objClass.'"'.$dataAttr.'>';
		
		return $html;
	}

	// Necessary for transition from 0.1.3 to 0.1.4
	function alterTemplatePartitionDataLayoutName(&$data){
		foreach($data as &$partition) {
			$partitionType = $partition['partitionType'];
			if($partitionType == 'Connectable' or $partitionType == 'Enclosure') {
				$layoutPrefix = ($partitionType == 'Connectable') ? 'port' : 'enc';
				
				// Change 'LayoutX' to 'valueX'
				if(isset($partition[$layoutPrefix.'LayoutX'])) {
					$valueX = $partition[$layoutPrefix.'LayoutX'];
					$partition['valueX'] = $valueX;
					unset($partition[$layoutPrefix.'LayoutX']);
				}
				
				// Change 'LayoutY' to 'valueY'
				if(isset($partition[$layoutPrefix.'LayoutY'])) {
					$valueY = $partition[$layoutPrefix.'LayoutY'];
					$partition['valueY'] = $valueY;
					unset($partition[$layoutPrefix.'LayoutY']);
				}
			}
			
			if(isset($partition['children'])) {
				$this->alterTemplatePartitionDataLayoutName($partition['children']);
			}
		}
		return true;
	}

	// Necessary for transition from 0.1.3 to 0.1.4
	function alterTemplatePartitionDataDimensionUnits(&$data){
		foreach($data as &$partition) {
			
			// Change 'vunits' to 'vUnits'
			if(isset($partition['vunits'])) {
				$vUnitValue = $partition['vunits'];
				$partition['vUnits'] = $vUnitValue;
				unset($partition['vunits']);
			}
			
			// Change 'hunits' to 'hUnits'
			if(isset($partition['hunits'])) {
				$hUnitValue = $partition['hunits'];
				$partition['hUnits'] = $hUnitValue;
				unset($partition['hunits']);
			}
			
			if(isset($partition['children'])) {
				$this->alterTemplatePartitionDataDimensionUnits($partition['children']);
			}
		}
		return true;
	}
	
	function wrapObject($objID, $objName, $selected=false, $trunkPairID=0) {
		$objName = str_replace('-', '&#8209;', $objName);
		$classArray = array('objectBox');
		if($objID) {
			$obj = $this->objectArray[$objID];
			$parentID = $obj['env_tree_id'];
			$templateID = $obj['template_id'];
			$template = $this->templateArray[$templateID];
			$templateFunction = $template['templateFunction'];
			$categoryID = $template['templateCategory_id'];
			$category = $this->categoryArray[$categoryID];
			$categoryName = $category['name'];
			$categoryClass = 'category'.$categoryName;
			array_push($classArray, $categoryClass);
			array_push($classArray, 'cursorPointer');
		} else {
			$parentID = 0;
			array_push($classArray, 'noCategory');
		}
		
		$class = implode(' ', $classArray);
		
		$endpointIcon = ($templateFunction == 'Endpoint') ? '<i class="fa fa-crosshairs" title="Endpoint"></i>&nbsp;' : '';
		$selectedIcon = $selected ? '<i class="fa fa-map-marker" title="Selected"></i>&nbsp;' : '';
		$html = '';
		$html .= ($objID) ? '<a href="/explore.php?parentID='.$parentID.'&objID='.$objID.'">' : '';
		$html .= '<div class="'.$class.'" data-trunk-pair-id="'.$trunkPairID.'">';
		$html .= $endpointIcon.$selectedIcon.$objName;
		$html .= '</div>';
		$html .= ($objID) ? '</a>' : '';
		
		return $html;
	}
	
	function getCabinetOccupiedRUs($cabinetID, $RUOrientation=false) {
		
		// Cabinet Data
		$cabinet = $this->envTreeArray[$cabinetID];
		$cabinetSize = $cabinet['size'];
		$RUOrientation = ($RUOrientation !== false) ? $RUOrientation : $cabinet['ru_orientation'];
		
		if(isset($this->objectByCabinetArray[$cabinetID])) {
			
			// Top Object Data
			$query = $this->qls->SQL->select('*', 'app_object', array('env_tree_id' => array('=', $cabinetID)), array('RU', 'DESC'), array(0,1));
			$topObj = $this->qls->SQL->fetch_assoc($query);
			$topObjOccupiedRU = $topObj['RU'];
			$firstOccupiedRU = $topObjOccupiedRU;
			
			// Bottom Object Data
			$query = $this->qls->SQL->select('*', 'app_object', array('env_tree_id' => array('=', $cabinetID), 'AND', 'parent_id' => array('=', 0)), array('RU', 'ASC'), array(0,1));
			$bottomObj = $this->qls->SQL->fetch_assoc($query);
			$bottomObjOccupiedRU = $bottomObj['RU'];
			$bottomObjTemplateID = $bottomObj['template_id'];
			$bottomObjTemplate = $this->templateArray[$bottomObjTemplateID];
			$bottomObjRUSize = $bottomObjTemplate['templateRUSize'];
			
			$firstOccupiedRU = $topObjOccupiedRU;
			$lastOccupiedRU = $bottomObjOccupiedRU - ($bottomObjRUSize - 1);
			
			$bottomUpMin = $firstOccupiedRU;
			$topDownMin = ($cabinetSize + 1) - ($cabinetSize - ($cabinetSize - $lastOccupiedRU));
			$orientationSpecificMin = ($RUOrientation == 0) ? $bottomUpMin : $topDownMin;
		} else {
			$firstOccupiedRU = $lastOccupiedRU = $topDownMin = $bottomUpMin = $orientationSpecificMin = 0;
		}
		
		return array('RUOrientation' => $RUOrientation, 'firstOccupiedRU' => $firstOccupiedRU+0, 'lastOccupiedRU' => $lastOccupiedRU+0, 'topDownMin' => $topDownMin+0, 'bottomUpMin' => $bottomUpMin+0, 'orientationSpecificMin' => $orientationSpecificMin+0);
	}
	
	function getTrunkFlatPath($objectID, $objectFace, $objectDepth){
		
		if(isset($this->peerArray[$objectID][$objectFace][$objectDepth])) {
			$peerRecord = $this->peerArray[$objectID][$objectFace][$objectDepth];
			$peerID = $peerRecord['peerID'];
			$peerFace = $peerRecord['peerFace'];
			$peerDepth = $peerRecord['peerDepth'];
			$flatPath = $this->buildTrunkFlatPath($peerID, $peerFace, $peerDepth);
		} else {
			$flatPath = 'None';
		}
		
		return $flatPath;
	}
	
	function buildTrunkFlatPath($objID, $objFace, $objDepth){
		
		// Peer object variables
		$obj = $this->objectArray[$objID];
		$objTemplateID = $obj['template_id'];
		
		// Partition variables
		$partitionCompatibility = $this->compatibilityArray[$objTemplateID][$objFace][$objDepth];
		$templateType = $partitionCompatibility['templateType'];
		$partitionFunction = $partitionCompatibility['partitionFunction'];
		
		$portNameFormat = json_decode($partitionCompatibility['portNameFormat'], true);
		$portTotal = $partitionCompatibility['portLayoutX']*$partitionCompatibility['portLayoutY'];
		$firstIndex = 0;
		$lastIndex = $portTotal - 1;
		$firstPortName = $this->generatePortName($portNameFormat, $firstIndex, $portTotal);
		$lastPortName = $this->generatePortName($portNameFormat, $lastIndex, $portTotal);
		$portRange = $firstPortName.'&nbsp;&#8209;&nbsp;'.$lastPortName;
		
		// Name/Port Seperator
		if($templateType == 'Insert') {
			if($partitionFunction == 'Endpoint') {
				$seperator = '';
			} else {
				$seperator = '.&#8203;';
			}
		} else {
			$seperator = '.&#8203;';
		}
		
		$flatPath = $this->generateObjectName($objID).$seperator.$portRange;
		
		return $flatPath;
	}
	
	function getAvailablePortArray($objID, $objFace, $objDepth){
		$occupiedPortArray = array();
		$attrArray = array('a','b');
		
		$templateID = $this->objectArray[$objID]['template_id'];
		
		$templateCompatibility = &$this->compatibilityArray[$templateID][$objFace][$objDepth];
		$portTotal = $templateCompatibility['portLayoutX'] * $templateCompatibility['portLayoutY'];
		
		// Gather patched ports
		if(isset($this->inventoryArray[$objID][$objFace][$objDepth])) {
			foreach($this->inventoryArray[$objID][$objFace][$objDepth] as $portID => $inventory) {
				array_push($occupiedPortArray, $portID);
			}
			$inventory = null;
			$portID = null;
			unset($inventory);
			unset($portID);
		}
		
		// Gather populated ports
		if(isset($this->populatedPortArray[$objID][$objFace][$objDepth])) {
			foreach($this->populatedPortArray[$objID][$objFace][$objDepth] as $portID => $inventory) {
				array_push($occupiedPortArray, $portID);
			}
			$inventory = null;
			$portID = null;
			unset($inventory);
			unset($portID);
		}
		
		$availablePortArray = array();
		for($x=0; $x<$portTotal; $x++) {
			if(!in_array($x, $occupiedPortArray)) {
				array_push($availablePortArray, $x);
			}
		}
		
		return $availablePortArray;
	}
	
	function getElevationDifference($ARU, $ASize, $BRU, $BSize){
		$min = 100;
		$max = 0;
		$ATopRU = $ARU;
		$ABottomRU = $ARU-($ASize-1);
		$BTopRU = $BRU;
		$BBottomRU = $BRU-($BSize-1);
		$elevationArray = array(
			$ATopRU,
			$ABottomRU,
			$BTopRU,
			$BBottomRU
		);
		foreach($elevationArray as $elevation) {
			if($elevation < $min) {
				$min = $elevation;
			}
			
			if($elevation > $max) {
				$max = $elevation;
			}
		}
		return array('min' => $min, 'max' => $max);
	}

	function generateCategoryOptions(){
		foreach($this->categoryArray as $category) {
			$selected = ($category['defaultOption'] == 1) ? 'selected' : '';
			echo '<option data-value="category'.$category['name'].'" id="categoryOption'.$category['id'].'" value="'.$category['id'].'" '.$selected.'>'.$category['name'].'</option>';
		}
	}

	function convertHyphens($string){
		return str_replace('-', '&#8209;', $string);
	}
	
	function unConvertHyphens($string){
		return str_replace('&#8209;', '-', $string);
	}
	
	function crawlPath2($objID, $objFace, $objDepth, $objPort, $detectDivergence=false, $portDiverges=false, &$pathArray=array(array(array(),array())), &$visitedArray=array(), $pathArrayIndex=0, $outerDirection=0, $innerDirection=0, $initial=true){
		
		// $outerDirection - 0=up 1=down
		// $innerDirection - 0=near 1=far
		
		// Add port address to visited array
		$portAddressString = $objID.'_'.$objFace.'_'.$objDepth.'_'.$objPort;
		$portAddressMD5 = md5($portAddressString);
		array_push($visitedArray, $portAddressMD5);
		
		// Store cable details
		$managedCableID = (isset($this->inventoryArray[$objID][$objFace][$objDepth][$objPort])) ? $this->inventoryArray[$objID][$objFace][$objDepth][$objPort][0]['localEndID'] : 0;
		if($managedCableID != 0) {
			$managedCable = $this->inventoryByIDArray[$managedCableID];
			$managedCableMediaTypeID = $managedCable['mediaType'];
			$managedCableLength = $managedCable['length'];
			$includeUnit = true;
			$length = $this->calculateCableLength($managedCableMediaTypeID, $managedCableLength, $includeUnit);
			$mediaTypeID = $managedCable['mediaType'];
			$connectorTypeID = $managedCable['localConnector'];
		} else {
			$length = 'Unk. Length';
			$mediaTypeID = false;
			$connectorTypeID = false;
		}
		
		// Store port details
		$portArray = array(
			'portAddressString' => $portAddressString,
			'objID' => $objID,
			'objFace' => $objFace,
			'objDepth' => $objDepth,
			'objPort' => $objPort,
			'selected' => $initial,
			'portDiverges' => $portDiverges,
			'length' => $length,
			'mediaTypeID' => $mediaTypeID,
			'connectorTypeID' => $connectorTypeID
		);
		
		// Determine connection array index
		if($outerDirection == 0) {
			$connectionArrayIndex = ($innerDirection == 0) ? 1 : 0;
		} else {
			$connectionArrayIndex = ($innerDirection == 0) ? 0 : 1;
		}
		
		// Add port to pathArray
		array_push($pathArray[$pathArrayIndex][$connectionArrayIndex], $portArray);
		
		//
		// Crawl connected ports
		//
		if(isset($this->inventoryArray[$objID][$objFace][$objDepth][$objPort])) {
			
			// Flip connection array index
			$newInnerDirection = ($innerDirection == 0) ? 1 : 0;
			
			// Loop over each local port connection
			$inventoryEntry = $this->inventoryArray[$objID][$objFace][$objDepth][$objPort];
			$organicPathObjParentID = $this->getParentObjID($inventoryEntry[0]['id']);
			foreach($inventoryEntry as $connection) {
				
				// Collect remote object data
				$remoteObjID = $connection['id'];
				$remoteObjFace = $connection['face'];
				$remoteObjDepth = $connection['depth'];
				$remoteObjPort = $connection['port'];
				
				$newPortDiverges = $portDiverges;
				if(!$newPortDiverges) {
					$remoteObjParentID = $this->getParentObjID($remoteObjID);
					if($remoteObjParentID != $organicPathObjParentID) {
						$newPortDiverges = true;
					}
				}
				
				// Generate remote port hash
				$remotePortAddressString = $remoteObjID.'_'.$remoteObjFace.'_'.$remoteObjDepth.'_'.$remoteObjPort;
				$remotePortAddressMD5 = md5($remotePortAddressString);
				
				if(!in_array($remotePortAddressMD5, $visitedArray)) {
					
					// Get path array index... it could change during recursion
					$pathArrayIndex = $this->getCurrentPathIndex($pathArray, $portAddressMD5);
					
					$this->crawlPath2($remoteObjID, $remoteObjFace, $remoteObjDepth, $remoteObjPort, $detectDivergence, $newPortDiverges, $pathArray, $visitedArray, $pathArrayIndex, $outerDirection, $newInnerDirection, false);
				}
			}
		}
		
		//
		// Crawl trunked ports
		//
		if(isset($this->peerArray[$objID][$objFace][$objDepth])) {
			
			// Set direction for seed port
			if($initial) {
				$outerDirection = 1;
				$innerDirection = 1;
			}
			
			// Loop over each local port connection
			$peer = $this->peerArray[$objID][$objFace][$objDepth];
				
			// Collect remote object data
			$remoteObjID = $peer['peerID'];
			$remoteObjFace = $peer['peerFace'];
			$remoteObjDepth = $peer['peerDepth'];
			$remoteObjPort = $objPort;
			
			// Generate remote port hash
			$remotePortAddressString = $remoteObjID.'_'.$remoteObjFace.'_'.$remoteObjDepth.'_'.$remoteObjPort;
			$remotePortAddressMD5 = md5($remotePortAddressString);
			
			if(!in_array($remotePortAddressMD5, $visitedArray)) {
				
				// Get path array index... it could change during recursion
				$pathArrayIndex = $this->getCurrentPathIndex($pathArray, $portAddressMD5);
				
				// Grow path array
				if($outerDirection == 0) {
					if($innerDirection == 1) {
						if($pathArrayIndex == 0) {
							array_unshift($pathArray, array(array(),array()));
						} else {
							$pathArrayIndex--;
						}
					} else {
						$outerDirection = 1;
						$pathArrayIndex++;
						if(!isset($pathArray[$pathArrayIndex])) {
							array_push($pathArray, array(array(),array()));
						}
					}
				} else {
					if($innerDirection == 1) {
						$pathArrayIndex++;
						if(!isset($pathArray[$pathArrayIndex])) {
							array_push($pathArray, array(array(),array()));
						}
					} else {
						$outerDirection = 0;
						if($pathArrayIndex == 0) {
							array_unshift($pathArray, array(array(),array()));
						}
						$pathArrayIndex--;
					}
				}

				$innerDirection = 0;
				$this->crawlPath2($remoteObjID, $remoteObjFace, $remoteObjDepth, $remoteObjPort, $detectDivergence, $portDiverges, $pathArray, $visitedArray, $pathArrayIndex, $outerDirection, $innerDirection, false);
			}
		}
		
		return $pathArray;
	}
	
	function getCurrentPathIndex($pathArray, $currentPortAddressMD5){
		foreach($pathArray as $pathArrayIndex => $connection) {
			foreach($connection as $connectionSide) {
				foreach($connectionSide as $port) {
					$objID = $port['objID'];
					$objFace = $port['objFace'];
					$objDepth = $port['objDepth'];
					$objPort = $port['objPort'];
					
					$portAddressString = $objID.'_'.$objFace.'_'.$objDepth.'_'.$objPort;
					$portAddressMD5 = md5($portAddressString);
					
					if($currentPortAddressMD5 == $portAddressMD5) {
						return $pathArrayIndex;
					}
				}
			}
		}
		
		return false;
	}

	function getParentObjID($objID) {
		
		while($this->objectArray[$objID]['parent_id'] != 0) {
			$objID = $this->objectArray[$objID]['parent_id'];
		}
		
		return $objID;
	}

	function detectDivergence(&$dataSet) {
	
		$pathDiverges = false;
		
		// Detect path divergence
		foreach($dataSet as $portIndex => $port) {
			
			// Identify parent object ID
			$portObjID = $port['objID'];
			$portObj = $this->objectArray[$portObjID];
			$portObjParentID = $portObj['parent_id'];
			while($portObjParentID != 0) {
				$portObj = $this->objectArray[$portObjParentID];
				$portObjParentID = $portObj['parent_id'];
			}
			
			// Determine path divergence
			if($portIndex == 0) {
				$baselineParentID = $portObjParentID;
			} else {
				if($portObjParentID != $baselineParentID) {
					
					// Flag this path as divergent
					$pathDiverges = true;
					
					// Remove divergent connection
					unset($dataSet[$portIndex]);
				}
			}
		}
		
		return $pathDiverges;
	}
	
	function loopDetected2($aID, $aFace, $aDepth, $aPort, $bID, $bFace, $bDepth, $bPort){
		
		$visitedArray = array();
		$loopDetected = false;
		$pathArrayA = $this->crawlPath2($aID, $aFace, $aDepth, $aPort);
		$pathArrayB = $this->crawlPath2($bID, $bFace, $bDepth, $bPort);
		$pathArray = array($pathArrayA, $pathArrayB);
		
		foreach($pathArray as $path) {
			foreach($path as $connection) {
				foreach($connection as $connectionSide) {
					foreach($connectionSide as $port) {
						
						$objID = $port['objID'];
						$objFace = $port['objFace'];
						$objDepth = $port['objDepth'];
						$objPort = $port['objPort'];
						
						$portString = $objID.'-'.$objFace.'-'.$objDepth.'-'.$objPort;
						$portStringHash = md5($portString);
						
						if(in_array($portStringHash, $visitedArray)) {
							$loopDetected = true;
						}
						
						array_push($visitedArray, $portStringHash);
						
					}
				}
			}
		}
		return $loopDetected;
	}
	
	function loopDetected($aID, $aFace, $aDepth, $aPort, $bID, $bFace, $bDepth, $bPort){
		
		// If cable is connected to an object
		if($aID != 0) {
			
			// If object is trunked
			if(isset($this->peerArray[$aID][$aFace][$aDepth])) {
				$peerRecord = $this->peerArray[$aID][$aFace][$aDepth];
				
				// If object's peer is not an endpoint
				if(!$peerRecord['peerEndpoint']) {
					$objID = $peerRecord['peerID'];
					$objFace = $peerRecord['peerFace'];
					$objDepth = $peerRecord['peerDepth'];
					
					// If peer has cable connected
					if(isset($this->inventoryArray[$objID][$objFace][$objDepth][$aPort])) {
						
						foreach($this->inventoryArray[$objID][$objFace][$objDepth][$aPort] as $peerCable) {
							$peerID = $peerCable['id'];
							$peerFace = $peerCable['face'];
							$peerDepth = $peerCable['depth'];
							$peerPort = $peerCable['port'];
							
							$loopDetected = $this->loopDetected($peerID, $peerFace, $peerDepth, $peerPort, $objID, $objFace, $objDepth, $aPort);
							if($loopDetected) {
								return true;
							}
						}
						
						return false;
					} else if($objID == $bID and $objFace == $bFace and $objDepth == $bDepth and $aPort == $bPort) {
						return true;
					} else {
						return false;
					}
				} else {
					return false;
				}
			} else {
				return false;
			}
		} else {
			return false;
		}
	}
}