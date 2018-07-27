<?php

namespace Broarm\CookieConsent;

use Exception;
use SilverStripe\Control\Director;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\ORM\HasManyList;
use SilverStripe\Forms\HTMLEditor\HTMLEditorField;
use SilverStripe\Forms\Tab;
use SilverStripe\Forms\TabSet;
use SilverStripe\Forms\TextField;
use SilverStripe\View\SSViewer;

/**
 * CookieGroup that holds type of cookies
 * You can add these groups trough the yml config
 *
 * @package Broarm
 * @subpackage CookieConsent
 *
 * @property string ConfigName
 * @property string Title
 * @property string Content
 *
 * @method HasManyList Cookies()
 */
class CookieGroup extends DataObject
{
    const REQUIRED_DEFAULT = 'Necessary';
    const LOCAL_PROVIDER = 'local';

    private static $table_name = 'CookieGroup';

    private static $db = array(
        'ConfigName' => 'Varchar(255)',
        'Title' => 'Varchar(255)',
        'Content' => 'HTMLText',
    );

    private static $indexes = array(
        'ConfigName' => true
    );

    private static $has_many = array(
        'Cookies' => 'Broarm\\CookieConsent\\CookieDescription.Group',
    );

    private static $translate = array(
        'Title',
        'Content'
    );

    /**
     * @return FieldList|mixed
     */
    public function getCMSFields()
    {
        $fields = FieldList::create(TabSet::create('Root', $mainTab = Tab::create('Main')));
        $fields->addFieldsToTab('Root.Main', array(
            TextField::create('Title', $this->fieldLabel('Title')),
            GridField::create('Cookies', $this->fieldLabel('Cookies'), $this->Cookies(), GridFieldConfigCookies::create())
        ));

        $fields->addFieldsToTab('Root.Description', array(
            HtmlEditorField::create('Content', $this->fieldLabel('Content'))
        ));

        $this->extend('updateCMSFields', $fields);
        return $fields;
    }

    /**
     * Check if this group is the required default
     *
     * @return bool
     */
    public function isRequired()
    {
        return $this->ConfigName === self::REQUIRED_DEFAULT;
    }

    /**
     * Create a Cookie Consent checkbox based on the current cookie group
     *
     * @return CookieConsentCheckBoxField
     */
    public function createField()
    {
        return new CookieConsentCheckBoxField($this);
    }

    /**
     * @throws Exception
     */
    public function requireDefaultRecords()
    {
        parent::requireDefaultRecords();
        if ($cookiesConfig = Config::inst()->get(CookieConsent::class, 'cookies')) {
            if (!isset($cookiesConfig[self::REQUIRED_DEFAULT])) {
                throw new Exception("The required default cookie set is missing, make sure to set the 'Necessary' group");
            }

            foreach ($cookiesConfig as $groupName => $providers) {
                if (!$group = self::get()->find('ConfigName', $groupName)) {
                    $group = self::create(array(
                        'ConfigName' => $groupName,
                        'Title' => _t("CookieConsent.$groupName", $groupName),
                        'Content' => _t("CookieConsent.{$groupName}_Content", $groupName)
                    ));

                    $group->write();
                    DB::alteration_message(sprintf('Cookie group "%s" created', $groupName), 'created');
                }

                foreach ($providers as $providerName => $cookies) {
                    if ($providerName === self::LOCAL_PROVIDER && Director::is_cli() && $url = Environment::getEnv('SS_BASE_URL')) {
                        $providerLabel = parse_url($url, PHP_URL_HOST);
                    } elseif ($providerName === self::LOCAL_PROVIDER) {
                        $providerLabel = Director::hostName();
                    } else {
                        $providerLabel = str_replace('_', '.', $providerName);
                    }

                    foreach ($cookies as $cookieName) {
                        $cookie = CookieDescription::get()->filter(array(
                            'ConfigName' => $cookieName,
                            'Provider' => $providerLabel
                        ))->first();

                        if (!$cookie) {
                            $cookie = CookieDescription::create(array(
                                'ConfigName' => $cookieName,
                                'Title' => $cookieName,
                                'Provider' => $providerLabel,
                                'Purpose' => _t("CookieConsent_{$providerName}.{$cookieName}_Purpose", "$cookieName"),
                                'Expiry' => _t("CookieConsent_{$providerName}.{$cookieName}_Expiry", 'Session')
                            ));

                            $group->Cookies()->add($cookie);
                            $cookie->flushCache();
                            DB::alteration_message(sprintf('Cookie "%s" created and added to group "%s"', $cookieName, $groupName), 'created');
                        }
                    }
                }

                $group->flushCache();
            }
        }
    }

    public function canCreate($member = null, $context = [])
    {
        return false;
    }

    /**
     * Make deletable if not defined in config
     *
     * @param null $member
     * @return bool
     */
    public function canDelete($member = null)
    {
        $cookieConfig = Config::inst()->get(CookieConsent::class, 'cookies');
        return !isset($cookieConfig[$this->ConfigName]);
    }
}