<?php

namespace Broarm\CookieConsent;

use Exception;
use SilverStripe\CMS\Controllers\ContentController;
use SilverStripe\Core\Extension;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Manifest\ModuleLoader;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Security;
use SilverStripe\View\Requirements;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;

/**
 * Class ContentControllerExtension
 * @package Broarm\CookieConsent
 * @property ContentController owner
 */
class ContentControllerExtension extends Extension
{
    private static $allowed_actions = array('acceptAllCookies');

    /**
     * Place the necessary js and css
     *
     * @throws \Exception
     */
    public function onAfterInit()
    {
        if (!($this->owner instanceof Security) && Config::inst()->get(CookieConsent::class, 'include_css')) {
            $module = ModuleLoader::getModule('bramdeleeuw/cookieconsent');
            Requirements::css($module->getResource('css/cookieconsent.css')->getRelativePath());
        }
    }

    /**
     * Method for checking cookie consent in template
     *
     * @param $group
     * @return bool
     * @throws Exception
     */
    public function CookieConsent($group = CookieConsent::NECESSARY)
    {
        return CookieConsent::check($group);
    }

    /**
     * Check if we can promt for concent
     * We're not on a Securty or Cooky policy page and have no concent set
     *
     * @return bool
     */
    public function PromptCookieConsent()
    {
        $securiy = $this->owner instanceof Security;
        $cookiePolicy = $this->owner instanceof CookiePolicyPageController;
        return !$securiy && !$cookiePolicy && !CookieConsent::check();
    }

    /**
     * Get an instance of the cookie policy page
     *
     * @return CookiePolicyPage|DataObject
     */
    public function getCookiePolicyPage()
    {
        return CookiePolicyPage::instance();
    }

    public function acceptAllCookies()
    {
        CookieConsent::grantAll();

        // Get the url the same as the redirect back method gets it
        $url = $this->owner->getBackURL()
            ?: $this->owner->getReturnReferer()
                ?: Director::baseURL();
        $cachebust = uniqid();
        $url = Director::absoluteURL("$url?acceptCookies=$cachebust");
        $this->owner->redirect($url);
    }

    public function getAcceptAllCookiesLink()
    {
        return Controller::join_links('acceptAllCookies', 'acceptAllCookies');
    }
}
