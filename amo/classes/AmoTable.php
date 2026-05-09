<?
namespace Itactis\AmoHelper;

class AmoTable
{
    const SETTINGS_FILE = __DIR__ . '/../../amo_settings.json';

    public static function getFieldsValue()
    {
        $content = @file_get_contents(self::SETTINGS_FILE);
        $arSettings = $content ? json_decode($content, true) : [];
        if (!empty($arSettings)) {
            return $arSettings;
        } else {
            return [];
        }
    }

    public function setFieldValue($code, $value)
    {
        $content = @file_get_contents(self::SETTINGS_FILE);
        $arSettings = $content ? json_decode($content, true) : [];
        $arSettings[$code] = $value;
        $result = @file_put_contents(self::SETTINGS_FILE, json_encode($arSettings));
        return $result;
    }
}
