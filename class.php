<? if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\SystemException;
use Bitrix\Main\Loader;
use Bitrix\Main\Type\Date;
use Bitrix\Main\Application;

Loader::includeModule("iblock");

class Pay extends CBitrixComponent
{
    private $ShopRootID = "--- id ---";
    private $UserTypeProperty = 38;
    private $UserTypeIncompetence = 37;
    private $TypePayProperty = 43;
    private $TypePayIncompetence = 42;
    private $arData = array();
    private $arUser = array();
    private $RoadIblockID = 30;

    private function GetRoadValues()
    {
        $this->arResult['FORM'] = '';
        $arSelect = Array("ID", "NAME", "DATE_ACTIVE_FROM", "PROPERTY_ROAD");
        $arFilter = Array("IBLOCK_ID" => $this->RoadIblockID, "ACTIVE_DATE" => "Y", "ACTIVE" => "Y");
        $res = CIBlockElement::GetList(Array(), $arFilter, false, false, $arSelect);
        while ($ob = $res->GetNextElement()) {
            $arFields = $ob->GetFields();
            $this->arResult['FORM'] .= '<option value="' . $arFields['ID'] . '">' . $arFields['PROPERTY_ROAD_VALUE'] . '</option>';
        }
    }

    private function GetRequest($parameter)
    {

        $request = Application::getInstance()->getContext()->getRequest();
        $result = $request->getPost($parameter);
        //$email = htmlspecialchars($request->getQuery($request));
        return $result;
    }

    private function GetData()
    {
        $this->arData['FIO'] = $this->GetRequest('fio');
        $this->arData['Phone'] = $this->GetRequest('phone');
        $this->arData['Birthday'] = $this->GetRequest('date_birth');
        $this->arData['Road'] = intval($this->GetRequest('road'));
        $this->arData['Sum'] = intval($this->GetRequest('rubles')) + floatval(intval($this->GetRequest('cents')) * 0.01);
        $this->arData['Type'] = $this->GetRequest('insurance_type');
        $this->arData['Policy'] = $this->GetRequest('polis');
        $this->arData['Time'] = time();
        $this->arData['RootPayId'] = $this->arData['Time'] . rand(0, 9999);
    }

    private function CheckTypeUser($Type)
    {
        switch ($Type) {
            case "property":
                $this->arData['SumProperty'] = $this->arData['Sum'];
                $this->arData['TypePay'] = $this->TypePayProperty;
                $this->arData['TypeUser'] = $this->UserTypeProperty;
                break;
            case "incompetence":
                $this->arData['SumIncompetence'] = $this->arData['Sum'];
                $this->arData['TypePay'] = $this->TypePayIncompetence;
                $this->arData['TypeUser'] = $this->UserTypeIncompetence;
                break;
            default:
                return "Error: wrong type;";
        }
    }

    private function GetValueRoad($ID)
    {
        if (intval($ID) != 0) {
            $db_props = CIBlockElement::GetProperty($this->RoadIblockID, $ID, array("sort" => "asc"), Array("CODE" => "ROAD"));
            if ($ar_props = $db_props->Fetch())
                $result = $ar_props["VALUE"];
            else
                $result = false;
        } else {
            $result = false;
        }
        $this->arData['RoadValue'] = $result;
    }

    public function executeComponent()
    {
        if ($this->startResultCache()) {
            try {
                $this->GetRoadValues();
                $this->GetData();

                if (!empty($this->arData['Type'])) {
                    $arPay = new \pay\Uniteller();
                    $arResult['PAY_DATA'] = $arPay->PrepareDataFirstPay($this->arData['RootPayId'], $this->arData['Sum']);
                    $resultCheckUser = $arPay->CheckExistUser($this->arData);
                    $this->CheckTypeUser($this->arData['Type']);
                    /*if ($this->arData['Type'] == "nedv") {
                        $Sum = $this->arData['Sum'];
                        $type = 43;
                        $typeUser = 38;
                    } else {
                        $SumPolice = $this->arData['Sum'];
                        $type = 42;
                        $typeUser = 37;
                    }*/
                    if (count($resultCheckUser) == 0) {
                        $this->GetValueRoad($this->arData['Road']);
                        /*if (intval($this->arData['Road'] != 0)) {
                            $db_props = CIBlockElement::GetProperty($this->RoadIblockID, $this->arData['Road'], array("sort" => "asc"), Array("CODE" => "ROAD"));
                            if ($ar_props = $db_props->Fetch())
                                $ROAD = $ar_props["VALUE"];
                            else
                                $ROAD = false;
                        } else {
                            $ROAD = false;
                        }*/
                        $UserID = $arPay->SaveUserByFirstPay(
                            $this->arData['RootPayId'],
                            $this->arData['FIO'],
                            $this->arData['Birthday'],
                            $this->arData['Phone'],
                            $this->arData['SumProperty'],
                            $this->arData['Policy'],
                            $this->arData['TypeUser'],
                            $this->arData['RoadValue'],
                            $this->arData['SumIncompetence'],
                            $this->ShopRootID);
                        if (intval($UserID)) {
                            $UserProperties = $arPay->GetPropertiesUser($UserID);
                            $arPay->AddTransaction(
                                date("Y.m.d H:i:s", $this->arData['Time']),
                                $this->arData['RootPayId'],
                                $UserID,
                                $this->arData['Sum'],
                                $UserProperties['PROPERTY_FIO_VALUE'],
                                $UserProperties['PROPERTY_BIRTHDAY_VALUE'],
                                $UserProperties['PROPERTY_POLIC_VALUE'],
                                44,
                                $this->arData['TypePay'],
                                $this->arData['Phone'],
                                false,
                                $this->arData['RoadValue']
                            );
                        }
                    } elseif (count($resultCheckUser) == 1) {
                        $arPay->AddTransaction(
                            date("Y.m.d H:i:s", $this->arData['Time']),
                            $this->arData['RootPayId'],
                            $resultCheckUser[0]['ID'],
                            $this->arData['Sum'],
                            $this->arData['FIO'],
                            $resultCheckUser[0]['PROPERTY_BIRTHDAY_VALUE'],
                            $this->arData['Policy'],
                            44,
                            $this->arData['TypePay'],
                            $this->arData['Phone']

                        );
                    } else {
                        $arPay->AddTransaction(
                            date("Y.m.d H:i:s", $this->arData['Time']),
                            $this->arData['RootPayId'],
                            false,
                            $this->arData['Sum'],
                            $this->arData['FIO'],
                            false,
                            $this->arData['Policy'],
                            44,
                            $this->arData['TypePay'],
                            $this->arData['Phone']

                        );
                    }
                    $GLOBALS['APPLICATION']->RestartBuffer();
                    echo json_encode($arResult['PAY_DATA']);
                    exit;
                } else {
                    $this->includeComponentTemplate();
                }


                //$this->includeComponentTemplate();
            } catch (SystemException $e) {
                ShowError($e->getMessage());
            }
        }
        return $this->arResult;
    }
}
