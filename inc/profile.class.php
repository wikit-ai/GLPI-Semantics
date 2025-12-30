<?php
/**
 * -------------------------------------------------------------------------
 * Wikit Semantics plugin for GLPI
 * Copyright (C) 2026 by the Wikit Development Team.
 * -------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * Class PluginWikitsemanticsProfile
 */
class PluginWikitsemanticsProfile extends CommonDBTM
{
   public static $rightname = "profile";

    /**
     * Get the tab name for an item
     *
     * @param CommonGLPI $item         Item instance
     * @param int        $withtemplate Template flag
     * @return string Tab name or empty string
     */
   public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
      if ($item->getType() == 'Profile' && $item->getField('interface') != 'helpdesk') {
          return __('Wikit Semantics', 'wikitsemantics');
      }
       return '';
   }


    /**
     * Display the tab content for an item
     *
     * @param CommonGLPI $item         Item instance
     * @param int        $tabnum       Tab number
     * @param int        $withtemplate Template flag
     * @return bool True if content was displayed
     */
   public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0): bool {
      if ($item->getType() == 'Profile') {
          $ID = $item->getID();
          $prof = new self();

          self::addDefaultProfileInfos($ID, ['plugin_wikitsemantics_configs' => 0]);
          $prof->showForm($ID);
      }
       return true;
   }

    /**
     * Create first access rights for a profile
     *
     * @param int $ID Profile ID
     * @return void
     */
   public static function createFirstAccess($ID) {
       self::addDefaultProfileInfos(
           $ID,
           ['plugin_wikitsemantics_configs' => READ + UPDATE],
           true
       );
   }

    /**
     * Add default profile rights
     *
     * @param int   $profiles_id   Profile ID
     * @param array $rights        Rights to add (name => value)
     * @param bool  $drop_existing Whether to drop existing rights before adding
     * @return void
     */
   public static function addDefaultProfileInfos($profiles_id, $rights, $drop_existing = false) {
       $dbu = new DbUtils();
       $profileRight = new ProfileRight();
      foreach ($rights as $right => $value) {
         if ($dbu->countElementsInTable(
              'glpi_profilerights',
              ["profiles_id" => $profiles_id, "name" => $right]
          ) && $drop_existing) {
            $profileRight->deleteByCriteria(['profiles_id' => $profiles_id, 'name' => $right]);
         }
         if (!$dbu->countElementsInTable(
              'glpi_profilerights',
              ["profiles_id" => $profiles_id, "name" => $right]
          )) {
             $myright['profiles_id'] = $profiles_id;
             $myright['name'] = $right;
             $myright['rights'] = $value;
             $profileRight->add($myright);

             //Add right to the current session
            if (isset($_SESSION['glpiactiveprofile'])) {
                $_SESSION['glpiactiveprofile'][$right] = $value;
            }
         }
      }
   }

    /**
     * Show profile form
     *
     * @param int  $profiles_id Profile ID
     * @param bool $openform    Whether to open the form tag
     * @param bool $closeform   Whether to close the form tag
     * @return void
     */
   public function showForm($profiles_id = 0, $openform = true, $closeform = true) {
       echo "<div class='firstbloc'>";
      if (($canedit = Session::haveRightsOr(self::$rightname, [CREATE, UPDATE, PURGE]))
           && $openform) {
          $profile = new Profile();
          echo "<form method='post' action='" . $profile->getFormURL() . "'>";
      }

       $profile = new Profile();
       $profile->getFromDB($profiles_id);
       $rights = $this->getAllRights();
       $profile->displayRightsChoiceMatrix($rights, [
           'canedit' => $canedit,
           'default_class' => 'tab_bg_2',
           'title' => __('General'),
       ]);

      if ($canedit
           && $closeform) {
          echo "<div class='center'>";
          echo Html::hidden('id', ['value' => $profiles_id]);
          echo Html::submit(_sx('button', 'Save'), ['name' => 'update']);
          echo "</div>\n";
          Html::closeForm();
      }
       echo "</div>";
   }

    /**
     * Get all plugin rights
     *
     * @param bool $all Get all rights or only visible ones
     * @return array Array of rights definitions
     */
   public static function getAllRights($all = false) {
       $rights = [
           [
               'rights' => [READ => __('Read'), UPDATE => __('Update')],
               'label' => __('Wikit Semantics', 'wikitsemantics'),
               'field' => 'plugin_wikitsemantics_configs',
           ],
       ];
       return $rights;
   }

    /**
     * Translate an old right format to new format
     *
     * @param string|int $old_right Old right value
     * @return int Translated right value
     */
   public static function translateARight($old_right) {
      switch ($old_right) {
         case '':
              return 0;
         case 'r':
              return READ;
         case 'w':
              return ALLSTANDARDRIGHT + READNOTE + UPDATENOTE;
         case '0':
         case '1':
              return $old_right;

         default:
              return 0;
      }
   }

    /**
     * Initialize profiles and migrate if necessary
     * Adds plugin rights to profiles and updates current session
     *
     * @return void
     */
   public static function initProfile() {
       global $DB;
       $profile = new self();
       $dbu = new DbUtils();

       //Add new rights in glpi_profilerights table
      foreach ($profile->getAllRights(true) as $data) {
         if ($dbu->countElementsInTable(
              "glpi_profilerights",
              ["name" => $data['field']]
          ) == 0) {
            ProfileRight::addProfileRights([$data['field']]);
         }
      }

       // Only update session if it exists and has active profile
      if (!isset($_SESSION['glpiactiveprofile']['id'])) {
          return;
      }

       $profileId = (int)$_SESSION['glpiactiveprofile']['id'];

       $it = $DB->request([
           'FROM' => 'glpi_profilerights',
           'WHERE' => [
               'profiles_id' => $profileId,
               'name' => ['LIKE', $DB->escape('%plugin_wikitsemantics%')],
           ],
       ]);
      foreach ($it as $prof) {
          $_SESSION['glpiactiveprofile'][$prof['name']] = $prof['rights'];
      }
   }


    /**
     * Remove plugin rights from current session
     *
     * @return void
     */
   public static function removeRightsFromSession() {
      foreach (self::getAllRights(true) as $right) {
         if (isset($_SESSION['glpiactiveprofile'][$right['field']])) {
            unset($_SESSION['glpiactiveprofile'][$right['field']]);
         }
      }
   }
}
