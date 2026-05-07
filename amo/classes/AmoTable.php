<?

namespace Itactis\AmoHelper;

class AmoTable
{
    const SETTINGS_FILE = '/home/c/cs16144/www452/amo_settings.json';

    public static function getFieldsValue()
    {
        $arSettings = json_decode(file_get_contents(self::SETTINGS_FILE), true);
        if (!empty($arSettings)) {
            return $arSettings;
        } else {
            return [];
        }
        // if (!(\Bitrix\Main\Loader::includeModule('highloadblock'))) {
        //     return "";
        // }

        // $return = self::getItemInfo($fieldCode);

        // if (!empty($return['UF_VALUE'])) {
        //     return $return['UF_VALUE'];
        // } else {
        //     return "";
        // }
    }

    public function setFieldValue($code, $value)
    {
        $arSettings = json_decode(file_get_contents(self::SETTINGS_FILE), true);
        $arSettings[$code] = $value;
        $result = file_put_contents(self::SETTINGS_FILE, json_encode($arSettings));
        return $result;
        // if (!(\Bitrix\Main\Loader::includeModule('highloadblock'))) {
        //     return false;
        // }

        // $itemExist = self::getItemInfo($code);
        // $entityDataClass = self::getEntityDataClass();

        // if (!empty($itemExist['ID'])) {
        //    $result = $entityDataClass::update($itemExist['ID'], ['UF_VALUE' => $value]);
        // } else {
        //     $result = $entityDataClass::add(['UF_CODE' => $code, 'UF_VALUE' => $value]);
        // }

        // return $result->isSuccess();
    }

    // protected static function getQueryItem() {
    //     $hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getById(self::AMO_TABLE_ID)->fetch();
    //     $entity = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
    //     $queryOb = new \Bitrix\Main\Entity\Query($entity);
    //     return $queryOb;
    // }

    // protected static function getItemInfo($fieldCode)
    // {
    //     $queryOb = self::getQueryItem();

    //     $queryOb->setSelect(['*']);
    //     $queryOb->setFilter(['UF_CODE' => $fieldCode]);
    //     $result = $queryOb->exec();

    //     $result = new \CDBResult($result);
    //     $return = $result->Fetch();

    //     if (!($return)) {
    //         $return = [];
    //     }

    //     return $return;
    // }



    // protected static function getEntityDataClass()
    // {
    //     $hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getById(self::AMO_TABLE_ID)->fetch();
    //     $entity = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
    //     $entityDataClass = $entity->getDataClass();
    //     return $entityDataClass;
    // }
}