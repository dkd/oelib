<?php
/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * This class represents a manager for front-end logins, providing access to the logged-in user.
 *
 *
 * @author Oliver Klee <typo3-coding@oliverklee.de>
 */
class Tx_Oelib_FrontEndLoginManager implements Tx_Oelib_Interface_LoginManager
{
    /**
     * @var Tx_Oelib_FrontEndLoginManager the Singleton instance
     */
    private static $instance = null;

    /**
     * the simulated logged-in user
     *
     * @var Tx_Oelib_Model_FrontEndUser
     */
    private $loggedInUser = null;

    /**
     * The constructor. Use getInstance() instead.
     */
    private function __construct()
    {
    }

    /**
     * Frees as much memory that has been used by this object as possible.
     */
    public function __destruct()
    {
    }

    /**
     * Returns an instance of this class.
     *
     * @return Tx_Oelib_FrontEndLoginManager the current Singleton instance
     */
    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new Tx_Oelib_FrontEndLoginManager();
        }

        return self::$instance;
    }

    /**
     * Purges the current instance so that getInstance will create a new
     * instance.
     *
     * @return void
     */
    public static function purgeInstance()
    {
        self::$instance = null;
    }

    /**
     * Checks whether any front-end user is logged in (and whether a front end
     * exists at all).
     *
     * @return bool TRUE if a front end exists and a front-end user is logged
     *                 in, FALSE otherwise
     */
    public function isLoggedIn()
    {
        $isSimulatedLoggedIn = ($this->loggedInUser !== null);
        $isReallyLoggedIn = ($this->getFrontEndController() !== null) && is_array($this->getFrontEndController()->fe_user->user) && $this->getFrontEndController()->fe_user->user['uid'] > 0;
        return $isSimulatedLoggedIn || $isReallyLoggedIn;
    }

    /**
     * Gets the currently logged-in front-end user.
     *
     * @param string $mapperName the name of the mapper to use for getting the front-end user model, must not be empty
     *
     * @return Tx_Oelib_Model_FrontEndUser the logged-in front-end user, will
     *                                     be NULL if no user is logged in or
     *                                     if there is no front end
     */
    public function getLoggedInUser($mapperName = Tx_Oelib_Mapper_FrontEndUser::class)
    {
        if ($mapperName === '') {
            throw new InvalidArgumentException('$mapperName must not be empty.', 1331488730);
        }
        if (!$this->isLoggedIn()) {
            return null;
        }

        if ($this->loggedInUser !== null) {
            $user = $this->loggedInUser;
        } else {
            /** @var Tx_Oelib_Mapper_FrontEndUser $mapper */
            $mapper = Tx_Oelib_MapperRegistry::get($mapperName);
            /** @var Tx_Oelib_Model_FrontEndUser $user */
            $user = $mapper->find($this->getFrontEndController()->fe_user->user['uid']);
        }

        return $user;
    }

    /**
     * Simulates a login of the user $user.
     *
     * This function is intended to be used for unit test only. Don't use it in the production code.
     *
     * @param Tx_Oelib_Model_FrontEndUser|null $user the user to log in, set to NULL for no logged-in user
     *
     * @return void
     */
    public function logInUser(Tx_Oelib_Model_FrontEndUser $user = null)
    {
        $this->loggedInUser = $user;
    }

    /**
     * Returns the current front-end instance.
     *
     * @return TypoScriptFrontendController|null
     */
    protected function getFrontEndController()
    {
        return isset($GLOBALS['TSFE']) ? $GLOBALS['TSFE'] : null;
    }
}
