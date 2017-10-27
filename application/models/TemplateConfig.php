<?php
if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/*
* LimeSurvey
* Copyright (C) 2007-2015 The LimeSurvey Project Team / Carsten Schmitz
* All rights reserved.
* License: GNU/GPL License v2 or later, see LICENSE.php
* LimeSurvey is free software. This version may have been modified pursuant
* to the GNU General Public License, and as distributed it includes or
* is derivative of works licensed under the GNU General Public License or
* other free or open source software licenses.
* See COPYRIGHT.php for copyright notices and details.
*/

/*
 */

/**
 * Class TemplateConfig
 * Common methods for TemplateConfiguration and TemplateManifest
 *
 */
class TemplateConfig extends CActiveRecord
 {
    /** @var string $sTemplateName The template name */
    public $sTemplateName='';

    /** @var string $sPackageName Name of the asset package of this template*/
    public $sPackageName;

    /** @var  string $path Path of this template */
    public $path;

    /** @var string[] $sTemplateurl Url to reach the framework */
    public $sTemplateurl;

    /** @var  string $viewPath Path of the views files (twig template) */
    public $viewPath;

    /** @var  string $filesPath Path of the tmeplate's files */
    public $filesPath;

    /** @var string[] $cssFramework What framework css is used */
    public $cssFramework;

    /** @var boolean $isStandard Is this template a core one? */
    public $isStandard;

    /** @var SimpleXMLElement $config Will contain the config.xml */
    public $config;

    /**
     * @var TemplateConfiguration $oMotherTemplate The mother template object
     * This is used when a template inherit another one.
     */
    public $oMotherTemplate;

    /** @var array $oOptions The template options */
    public $oOptions;


    /** @var string[] $depends List of all dependencies (could be more that just the config.xml packages) */
    protected $depends = array();

    /**  @var integer $apiVersion: Version of the LS API when created. Must be private : disallow update */
    protected $apiVersion;

    /** @var string $iSurveyId The current Survey Id. It can be void. It's use only to retreive the current template of a given survey */
    protected $iSurveyId='';

    /** @var string $hasConfigFile Does it has a config.xml file? */
    protected $hasConfigFile='';//

    /** @var stdClass[] $packages Array of package dependencies defined in config.xml*/
    protected $packages;

    /** @var string $xmlFile What xml config file does it use? (config/minimal) */
    protected $xmlFile;



     /**
      * get the template API version
      * @return integer
      */
     public function getApiVersion()
     {
         return $this->apiVersion;
     }

     /**
     * Returns the complete URL path to a given template name
     *
     * @return string template url
     */
     public function getTemplateURL()
     {
         if(!isset($this->sTemplateurl)){
             $this->sTemplateurl = Template::getTemplateURL($this->sTemplateName);
         }
         return $this->sTemplateurl;
     }


     /**
      * Get the template for a given file. It checks if a file exist in the current template or in one of its mother templates
      *
      * @param  string $sFile the  file to look for (must contain relative path, unless it's a view file)
      * @param TemplateConfig $oRTemplate template from which the recurrence should start
      * @return TemplateConfig
      * @throws Exception
      */
     public function getTemplateForFile($sFile, $oRTemplate)
     {
         while (!file_exists($oRTemplate->path.'/'.$sFile) && !file_exists($oRTemplate->viewPath.$sFile) && !file_exists($oRTemplate->filesPath.$sFile)){
             $oMotherTemplate = $oRTemplate->oMotherTemplate;
             if(!($oMotherTemplate instanceof TemplateConfiguration)){
                 throw new Exception("No template found for $sFile! Last template searched: ".$oRTemplate->sTemplateName );
                 break;
             }
             $oRTemplate = $oMotherTemplate;
         }

         return $oRTemplate;
     }


     /**
      * Create a package for the asset manager.
      * The asset manager will push to tmp/assets/xyxyxy/ the whole template directory (with css, js, files, etc.)
      * And it will publish the CSS and the JS defined in config.xml. So CSS can use relative path for pictures.
      * The publication of the package itself is in LSETwigViewRenderer::renderTemplateFromString()
      *
      * @param $oTemplate TemplateManifest
      */
     protected function createTemplatePackage($oTemplate)
     {
         // Each template in the inheritance tree needs a specific alias
         $sPathName  = 'survey.template-'.$oTemplate->sTemplateName.'.path';
         $sViewName  = 'survey.template-'.$oTemplate->sTemplateName.'.viewpath';

         Yii::setPathOfAlias($sPathName, $oTemplate->path);
         Yii::setPathOfAlias($sViewName, $oTemplate->viewPath);

         $aCssFiles  = $aJsFiles = array();

         // First we add the framework replacement (bootstrap.css must be loaded before template.css)
         $aCssFiles  = $this->getFrameworkAssetsReplacement('css');
         $aJsFiles   = $this->getFrameworkAssetsReplacement('js');

         // Then we add the template config files
         $aTCssFiles = $this->getFilesToLoad($oTemplate, 'css');
         $aTJsFiles  = $this->getFilesToLoad($oTemplate, 'js');

         $aCssFiles  = array_merge($aCssFiles, $aTCssFiles);
         $aJsFiles   = array_merge($aJsFiles, $aTJsFiles);

         $dir        = getLanguageRTL(App()->language) ? 'rtl' : 'ltr';

         // Remove/Replace mother template files
         $aCssFiles = $this->changeMotherConfiguration('css', $aCssFiles);
         $aJsFiles  = $this->changeMotherConfiguration('js',  $aJsFiles);

         // Then we add the direction files if they exist
         // TODO: attribute system rather than specific fields for RTL

         $this->sPackageName = 'survey-template-'.$this->sTemplateName;
         $sTemplateurl       = $oTemplate->getTemplateURL();

         $aDepends          = empty($oTemplate->depends)?array():$oTemplate->depends;


         // The package "survey-template-{sTemplateName}" will be available from anywhere in the app now.
         // To publish it : Yii::app()->clientScript->registerPackage( 'survey-template-{sTemplateName}' );
         // Depending on settings, it will create the asset directory, and publish the css and js files
         Yii::app()->clientScript->addPackage( $this->sPackageName, array(
             'devBaseUrl'  => $sTemplateurl,                                     // Used when asset manager is off
             'basePath'    => $sPathName,                                        // Used when asset manager is on
             'css'         => $aCssFiles,
             'js'          => $aJsFiles,
             'depends'     => $aDepends,
         ) );
     }

     /**
      * Get the file path for a given template.
      * It will check if css/js (relative to path), or view (view path)
      * It will search for current template and mother templates
      *
      * @param   string $sFile relative path to the file
      * @param   string $oTemplate the template where to look for (and its mother templates)
      * @return string|false
      */
     protected function getFilePath($sFile, $oTemplate)
     {
         // Remove relative path
         $sFile = trim($sFile, '.');
         $sFile = trim($sFile, '/');

         // Retreive the correct template for this file (can be a mother template)
         $oTemplate = $this->getTemplateForFile($sFile, $oTemplate);

         if($oTemplate instanceof TemplateConfiguration){
             if(file_exists($oTemplate->path.'/'.$sFile)){
                 return $oTemplate->path.'/'.$sFile;
             }elseif(file_exists($oTemplate->viewPath.$sFile)){
                 return $oTemplate->viewPath.$sFile;
             }
         }
         return false;
     }

     /**
      * Get the depends package
      * @uses self::@package
      * @return string[]
      */
     protected function getDependsPackages($oTemplate)
     {
         $dir = (getLanguageRTL(App()->getLanguage()))?'rtl':'ltr';

         /* Core package */
         $packages[] = 'limesurvey-public';
         $packages[] = 'template-core';
         $packages[] = ( $dir == "ltr")? 'template-core-ltr' : 'template-core-rtl'; // Awesome Bootstrap Checkboxes

         /* bootstrap */
         if(!empty($this->cssFramework)){

             // Basic bootstrap package
             if((string)$this->cssFramework->name == "bootstrap"){
                 $packages[] = 'bootstrap';
             }

             // Rtl version of bootstrap
             if ($dir == "rtl"){
                 $packages[] = 'bootstrap-rtl';
             }

             // Remove unwanted bootstrap stuff
             foreach( $this->getFrameworkAssetsToReplace('css', true) as $toReplace){
                 Yii::app()->clientScript->removeFileFromPackage('bootstrap', 'css', $toReplace );
             }

             foreach( $this->getFrameworkAssetsToReplace('js', true) as $toReplace){
                 Yii::app()->clientScript->removeFileFromPackage('bootstrap', 'js', $toReplace );
             }
         }

         // Moter Template Package
         $packages = $this->addMotherTemplatePackage($packages);

         return $packages;
     }



     /**
     * @return boolean|null
     */
     protected function setIsStandard()
     {
        $this->isStandard = Template::isStandardTemplate($this->sTemplateName);
     }


    /**
     * Core config and attributes
     *
     * Most classes and id and attributes from template views are defined here.
     * So even if users extends/modify the core template, we can still apply some debugs
     *
     * NB 1: Some of the classes should be bring back to templates
     *
     * NB 2: This is a temporary function. Before releasing to master, it will be replaced by a XML file inside the template itself
     * So third party providers will also be able to use this mechanics to provides bug fixes/enhancement to their templates
     */
    public function getClassAndAttributes()
    {

        $aClassAndAttributes = array();

        // Welcome
        $aClassAndAttributes['id']['welcomecontainer']     =  'welcome-container ';
        $aClassAndAttributes['class']['welcomecontainer']  = '';
        $aClassAndAttributes['class']['surveyname']        = " survey-name ";
        $aClassAndAttributes['class']['description']       = " survey-description ";
        $aClassAndAttributes['class']['welcome']           = " survey-welcome ";
        $aClassAndAttributes['class']['questioncount']     = " number-of-questions  ";
        $aClassAndAttributes['class']['questioncounttext'] = " question-count-text ";

        $aClassAndAttributes['attr']['questioncounttext'] = '';

        // Global
        $aClassAndAttributes['id']['outerframe']    = 'outerframeContainer' ;
        $aClassAndAttributes['id']['mainrow']       = 'main-row' ;
        $aClassAndAttributes['id']['maincol']       = 'main-col' ;
        $aClassAndAttributes['id']['dynamicreload'] = 'dynamicReloadContainer' ;

        $aClassAndAttributes['class']['html']  = ' no-js ';
        $aClassAndAttributes['class']['body']  = $this->sTemplateName;
        $aClassAndAttributes['class']['outerframe'] = ' outerframe ' ;
        $aClassAndAttributes['class']['maincol'] = ' ' ;
        $aClassAndAttributes['attr']['html']   = $thissurvey['attr']['body'] = $aClassAndAttributes['attr']['outerframe'] = $thissurvey['attr']['mainrow'] = $thissurvey['attr']['maincol']  = '';

        // User forms
        $aClassAndAttributes['class']['maincoldivdiva']               = '  ';
        $aClassAndAttributes['class']['maincoldivdivb']               = '  ';
        $aClassAndAttributes['class']['maincoldivdivbp']              = '  ';
        $aClassAndAttributes['class']['maincoldivdivbul']             = '  ';
        $aClassAndAttributes['class']['maincoldivdivbdiv']            = ' ';
        $aClassAndAttributes['class']['maincolform']                  = '  ';
        $aClassAndAttributes['class']['maincolformlabel']             = '  ';
        $aClassAndAttributes['class']['maincolformlabelsmall']        = ' superset ';
        $aClassAndAttributes['class']['maincolformlabelspan']         = ' ';
        $aClassAndAttributes['class']['maincolformdiva']              = '  load-survey-input input-cell ';
        $aClassAndAttributes['class']['maincolformdivainput']         = '  ';
        $aClassAndAttributes['class']['maincolformdivb']              = ' captcha-item ';
        $aClassAndAttributes['class']['maincolformdivblabel']         = '  ';
        $aClassAndAttributes['class']['maincolformdivblabelsmall']    = '  ';
        $aClassAndAttributes['class']['maincolformdivblabelspan']     = '  ';
        $aClassAndAttributes['class']['maincolformdivbdiv']           = '  ';
        $aClassAndAttributes['class']['maincolformdivbdivdiv']        = ' ls-input-group ';
        $aClassAndAttributes['class']['maincolformdivbdivdivdiv']     = ' ls-input-group-extra captcha-widget ';
        $aClassAndAttributes['class']['maincolformdivbdivdivinput']   = '  ';
        $aClassAndAttributes['class']['maincolformdivc']              = ' load-survey-row load-survey-submit ';
        $aClassAndAttributes['class']['maincolformdivcdiv']           = ' load-survey-input input-cell ';
        $aClassAndAttributes['class']['maincolformdivcdivbutton']     = '  ';
        $aClassAndAttributes['class']['maincolformdivd']              = '  ';
        $aClassAndAttributes['class']['maincolformdivddiv']           = '  captcha-item ';
        $aClassAndAttributes['class']['maincolformdivddivlabel']      = '  ';
        $aClassAndAttributes['class']['maincolformdivddivcol']        = '  ';
        $aClassAndAttributes['class']['maincolformdivddivcoldiv']     = ' ls-input-group ';
        $aClassAndAttributes['class']['maincolformdivddivcoldivdiv']  = ' ls-input-group-extra captcha-widget ';
        $aClassAndAttributes['class']['maincolformdivddivcolinput']   = '  ';
        $aClassAndAttributes['class']['maincolformdivddivb']          = ' load-survey-row load-survey-submit ';
        $aClassAndAttributes['class']['maincolformdivddivbdiv']       = ' load-survey-input input-cell ';
        $aClassAndAttributes['class']['maincolformdivddivbdivbutton'] = '  ';


        $aClassAndAttributes['attr']['maincolformdivainput']          = ' type="password" id="token" name="token" value="" required ';
        $aClassAndAttributes['attr']['maincoldivdivbul']              =  ' role="alert" ';
        $aClassAndAttributes['attr']['maincolformlabel']              = ' for="loadname"';
        $aClassAndAttributes['attr']['maincolformlabelsmall']         = ' aria-hidden="true" ';
        $aClassAndAttributes['attr']['maincolformdivblabel']          = ' for="loadsecurity" ';
        $aClassAndAttributes['attr']['maincolformdivblabelsmall']     = ' aria-hidden="true" ';
        $aClassAndAttributes['attr']['maincolformdivbdivdivinput']    = ' type="text" size="15" maxlength="15" id="loadsecurity" name="loadsecurity" value="" alt="" required ';
        $aClassAndAttributes['attr']['maincolformdivcdivbutton']      = ' type="submit" id="default" name="continue"  value="continue" ';
        $aClassAndAttributes['attr']['maincolformdivddivlabel']       = ' for="loadsecurity" ';
        $aClassAndAttributes['attr']['maincolformdivddivcolinput']    = ' type="text" size="15" maxlength="15" id="loadsecurity" name="loadsecurity" value="" alt="" required ';
        $aClassAndAttributes['attr']['maincolformdivddivbdivbutton'] = ' type="submit" id="default" name="continue" value="continue" ';

        $aClassAndAttributes['attr']['maincol'] = $aClassAndAttributes['attr']['maincoldiva'] = $aClassAndAttributes['attr']['maincoldivdivb'] = $aClassAndAttributes['attr']['maincoldivdivbp'] = $aClassAndAttributes['attr']['maincoldivdivbdiv'] = $aClassAndAttributes['attr']['maincolform'] = '';
        $aClassAndAttributes['attr']['maincolformlabelspan']   = $aClassAndAttributes['attr']['maincolformdiva'] = $aClassAndAttributes['attr']['maincolformdivb'] = $aClassAndAttributes['attr']['maincolformdivbdiv'] = $aClassAndAttributes['class']['maincolformdivbdivdivdiv'] = $aClassAndAttributes['attr']['maincolformdivcdiv'] = ' ';
        $aClassAndAttributes['attr']['maincolformdivd']  = $aClassAndAttributes['attr']['maincolformdivddiv'] = $aClassAndAttributes['attr']['maincolformdivddivcol'] = $aClassAndAttributes['attr']['maincolformdivddivcoldiv'] = $aClassAndAttributes['attr']['maincolformdivddivb'] = '';


        // Clear all
        $aClassAndAttributes['class']['clearall']    = 'return-to-survey';
        $aClassAndAttributes['class']['clearalldiv'] = ' url-wrapper url-wrapper-survey-return ';
        $aClassAndAttributes['class']['clearalla']   = ' ls-return ';
        $aClassAndAttributes['attr']['clearall'] = $thissurvey['attr']['clearalldiv'] = $thissurvey['attr']['clearalla'] = '';

        // Load
        $aClassAndAttributes['id']['saveformrowcolinput']    = 'loadname';
        $aClassAndAttributes['id']['captcharowcoldivinput']  = 'loadsecurity';

        $aClassAndAttributes['class']['savemessage']           = ' save-message ';
        $aClassAndAttributes['class']['savemessagetitle']      = '  ';
        $aClassAndAttributes['class']['savemessagetext']       = '  ';
        $aClassAndAttributes['class']['loadform']              = ' load-form ';
        $aClassAndAttributes['class']['loadformul']            = ' ';
        $aClassAndAttributes['class']['loadformform']          = ' ls-form form form-horizontal ';
        $aClassAndAttributes['class']['saveform']              = ' save-survey-form ';
        $aClassAndAttributes['class']['saveformrow']           = ' save-survey-row save-survey-name ';
        $aClassAndAttributes['class']['saveformrowlabel']      = ' load-survey-label ';
        $aClassAndAttributes['class']['saveformrowlabelsmall'] = '  ';
        $aClassAndAttributes['class']['saveformrowlabelspan']  = '  ';
        $aClassAndAttributes['class']['saveformrowcol']        = '  save-survey-input  ';
        $aClassAndAttributes['class']['saveformrowcolinput']   = '  ';
        $aClassAndAttributes['class']['passwordrow']           = ' load-survey-row load-survey-password ';
        $aClassAndAttributes['class']['passwordrowcol']        = ' load-survey-label label-cell ';
        $aClassAndAttributes['class']['passwordrowcolsmall']   = '  ';
        $aClassAndAttributes['class']['passwordrowcolspan']    = '  ';
        $aClassAndAttributes['class']['passwordrowinput']      = ' save-survey-input input-cell ';

        $aClassAndAttributes['class']['captcharow']            = ' save-survey-row save-survey-captcha ';
        $aClassAndAttributes['class']['captcharowlabel']       = ' save-survey-label label-cell ';
        $aClassAndAttributes['class']['captcharowcol']         = ' save-survey-input input-cell ';
        $aClassAndAttributes['class']['captcharowcoldiv']      = ' input-group ';
        $aClassAndAttributes['class']['captcharowcoldivdiv']   = ' input-group-addon captcha-image ';
        $aClassAndAttributes['class']['captcharowcoldivinput'] = '  ';
        $aClassAndAttributes['class']['loadrow']               = ' save-survey-row save-survey-submit ';
        $aClassAndAttributes['class']['loadrowcol']            = ' save-survey-input input-cell ';
        $aClassAndAttributes['class']['loadrowcolbutton']      = '  ';
        $aClassAndAttributes['class']['returntosurvey']        = ' return-to-survey ';
        $aClassAndAttributes['class']['returntosurveydiv']     = ' url-wrapper url-wrapper-survey-return ';
        $aClassAndAttributes['class']['returntosurveydiva']    = ' ls-return ';

        $aClassAndAttributes['attr']['loadformul']             = ' role="alert"';
        $aClassAndAttributes['attr']['saveformrowlabel']       = ' for="savename" ';
        $aClassAndAttributes['attr']['saveformrowlabelsmall']  = ' aria-hidden="true" ';
        $aClassAndAttributes['attr']['saveformrowcolinput']    = ' type="text"  name="loadname" value="" required ';
        $aClassAndAttributes['attr']['passwordrowinputi']      = ' type="password" id="loadpass" name="loadpass" value="" required ';

        $aClassAndAttributes['attr']['passwordrowcol']         = ' for="loadpass" ';
        $aClassAndAttributes['attr']['passwordrowcolsmall']    = ' aria-hidden="true"';
        $aClassAndAttributes['attr']['captcharowcoldivdivimg'] = ' alt="captcha" ';
        $aClassAndAttributes['attr']['captcharowcoldivinput']  = '  type="text" size="5" maxlength="3" id="loadsecurity" name="loadsecurity" value="" alt="" ';
        $aClassAndAttributes['attr']['loadrowcolbutton']       = '  type="submit" id="loadbutton" name="loadall"  value="reload" ';



        $aClassAndAttributes['attr']['savemessage'] = $aClassAndAttributes['attr']['savemessagetext'] = $aClassAndAttributes['attr']['savemessagetitle'] = $aClassAndAttributes['attr']['loadform']  = $aClassAndAttributes['attr']['savemessagetextp'] = $aClassAndAttributes['attr']['savemessagetextpb'] = '';
        $aClassAndAttributes['attr']['loadformulli'] = $aClassAndAttributes['attr']['saveform']  = $aClassAndAttributes['attr']['saveformrow'] = $aClassAndAttributes['attr']['saveformrowlabelspan'] = $aClassAndAttributes['attr']['saveformrowcol'] = $aClassAndAttributes['attr']['passwordrow'] = '';
        $aClassAndAttributes['attr']['passwordrowcolspan'] = $aClassAndAttributes['attr']['captcharow']  = $aClassAndAttributes['attr']['captcharowlabel']  = $aClassAndAttributes['attr']['captcharowcol'] = $aClassAndAttributes['attr']['captcharowcoldiv'] = $aClassAndAttributes['attr']['loadrow'] = '';
        $aClassAndAttributes['attr']['loadrowcol'] = $aClassAndAttributes['class']['returntosurvey'] = $aClassAndAttributes['attr']['returntosurveydiv'] = $aClassAndAttributes['class']['returntosurveydiva']  = '';

        // Save
        $aClassAndAttributes['class']['savecontainer']                 = ' save-message ';
        $aClassAndAttributes['class']['savecontainertitle']            = '  ';
        $aClassAndAttributes['class']['savecontainertext']             = '  ';
        $aClassAndAttributes['class']['savecontainertextpc']           = ' info-email-optional ls-info ';
        $aClassAndAttributes['class']['savecontainerwarning']          = '  ';
        $aClassAndAttributes['class']['saveformcontainer']             = ' save-form ';
        $aClassAndAttributes['class']['saveformcontainerul']           = ' ';
        $aClassAndAttributes['class']['saveformsurvey']                = ' save-survey-form  ';
        $aClassAndAttributes['class']['saveformsurveydiva']            = ' save-survey-row save-survey-name ';
        $aClassAndAttributes['class']['saveformsurveydivalabel']       = '  save-survey-label ';
        $aClassAndAttributes['class']['saveformsurveydivalabelsmall']  = ' ';
        $aClassAndAttributes['class']['saveformsurveydivalabelspan']   = '  ';
        $aClassAndAttributes['class']['saveformsurveydivb']            = ' save-survey-input input-cell ';
        $aClassAndAttributes['class']['saveformsurveydivc']            = '  save-survey-row save-survey-password ';
        $aClassAndAttributes['class']['saveformsurveydivclabel']       = ' save-survey-label label-cell ';
        $aClassAndAttributes['class']['saveformsurveydivcsmall']       = '  ';
        $aClassAndAttributes['class']['saveformsurveydivcspan']        = '  ';
        $aClassAndAttributes['class']['saveformsurveydivcdiv']         = ' save-survey-input input-cell ';
        $aClassAndAttributes['class']['saveformsurveydivd']            = ' save-survey-row save-survey-password ';
        $aClassAndAttributes['class']['saveformsurveydivdlabel']       = ' save-survey-label label-cell ';
        $aClassAndAttributes['class']['saveformsurveydivdlabelsmall']  = ' ';
        $aClassAndAttributes['class']['saveformsurveydivdlabelspan']   = '  ';
        $aClassAndAttributes['class']['saveformsurveydivddiv']         = ' save-survey-input input-cell ';
        $aClassAndAttributes['class']['saveformsurveydive']            = ' save-survey-row save-survey-password ';
        $aClassAndAttributes['class']['saveformsurveydivelabel']       = ' save-survey-label label-cell ';
        $aClassAndAttributes['class']['saveformsurveydivediv']         = ' save-survey-input input-cell ';
        $aClassAndAttributes['class']['saveformsurveydivf']            = ' save-survey-row save-survey-captcha ';
        $aClassAndAttributes['class']['saveformsurveydivflabel']       = ' save-survey-label label-cell ';
        $aClassAndAttributes['class']['saveformsurveydivfdiv']         = ' save-survey-input input-cell ';
        $aClassAndAttributes['class']['saveformsurveydivfdivdiv']      = '  ';
        $aClassAndAttributes['class']['saveformsurveydivfdivdivdiv']   = ' input-group-addon captcha-image ';
        $aClassAndAttributes['class']['saveformsurveydivfdivdivinput'] = ' ';
        $aClassAndAttributes['class']['saveformsurveydivg']            = '  save-survey-row save-survey-submit ';
        $aClassAndAttributes['class']['saveformsurveydivgdiv']         = ' save-survey-input input-cell ';
        $aClassAndAttributes['class']['saveformsurveydivgdivbutton']   = '  ';
        $aClassAndAttributes['class']['saveformsurveydivh']            = ' return-to-survey ';
        $aClassAndAttributes['class']['saveformsurveydivhdiv']         = ' url-wrapper url-wrapper-survey-return ';
        $aClassAndAttributes['class']['saveformsurveydivhdiva']        = ' ls-return ';



        $aClassAndAttributes['attr']['saveformcontainerul']            = ' role="alert" ';
        $aClassAndAttributes['attr']['saveformsurveydivalabel']        = ' for="savename" ';
        $aClassAndAttributes['attr']['saveformsurveydivalabelsmall']   = ' aria-hidden="true" ';
        $aClassAndAttributes['attr']['saveformsurveydivclabel']        = ' for="savepass" ';
        $aClassAndAttributes['attr']['saveformsurveydivcsmall']        = ' aria-hidden="true" ';
        $aClassAndAttributes['attr']['saveformsurveydivdlabel']        = ' for="savepass2" ';
        $aClassAndAttributes['attr']['saveformsurveydivdlabelsmall']   = ' aria-hidden="true" ';
        $aClassAndAttributes['attr']['saveformsurveydivelabel']        = ' for="saveemail" ';
        $aClassAndAttributes['attr']['saveformsurveydivflabel']        = ' for="loadsecurity" ';
        $aClassAndAttributes['attr']['saveformsurveydivfdivdivdivimg'] = ' alt="captcha" ';
        $aClassAndAttributes['attr']['saveformsurveydivfdivdivinput']  = ' type="text" size="5" maxlength="3" id="loadsecurity" name="loadsecurity" value="" alt="" ';
        $aClassAndAttributes['attr']['saveformsurveydivgdivbutton']    = ' type="submit" id="savebutton" name="savesubmit" value="save"';


        $aClassAndAttributes['attr']['savecontainer'] = $aClassAndAttributes['attr']['savecontainertitle'] = $aClassAndAttributes['attr']['savecontainertext'] = $aClassAndAttributes['attr']['savecontainerwarning'] = $aClassAndAttributes['attr']['saveformcontainer'] = $aClassAndAttributes['attr']['saveformcontainerli']   = '';
        $aClassAndAttributes['attr']['savecontainertextpa'] = $aClassAndAttributes['attr']['savecontainertextpb'] = $aClassAndAttributes['attr']['savecontainertextpc'] = $aClassAndAttributes['attr']['savecontainertextpd'] = $aClassAndAttributes['attr']['saveformsurveydiva'] = $aClassAndAttributes['attr']['saveformsurveydivalabelspan'] = '';
        $aClassAndAttributes['attr']['saveformsurveydivc'] = $aClassAndAttributes['attr']['saveformsurveydivcspan'] = $aClassAndAttributes['attr']['saveformsurveydivcdiv'] = $aClassAndAttributes['attr']['saveformsurveydivd'] = $aClassAndAttributes['attr']['saveformsurveydivdlabelspan'] = $aClassAndAttributes['attr']['saveformsurveydivddiv'] = '';
        $aClassAndAttributes['attr']['saveformsurveydive'] = $aClassAndAttributes['attr']['saveformsurveydivediv'] = $aClassAndAttributes['attr']['saveformsurveydivf']   = $aClassAndAttributes['attr']['saveformsurveydivfdiv'] = $aClassAndAttributes['attr']['saveformsurveydivfdivdiv'] = $aClassAndAttributes['attr']['saveformsurveydivfdivdivdiv'] = '';
        $aClassAndAttributes['attr']['saveformsurveydivgdiv'] = $aClassAndAttributes['attr']['saveformsurveydivh'] = $aClassAndAttributes['attr']['saveformsurveydivhdiv']   = '';

        // Completed
        $aClassAndAttributes['id']['navigator']           = 'navigator-container';

        $aClassAndAttributes['class']['completedwrapper']     = ' completed-wrapper ';
        $aClassAndAttributes['class']['completedtext']        = ' completed-text ';
        $aClassAndAttributes['class']['quotamessage']         = ' quotamessage limesurveycore ';
        $aClassAndAttributes['class']['navigator']            = ' navigator ';
        $aClassAndAttributes['class']['navigatorcoll']        = '  ';
        $aClassAndAttributes['class']['navigatorcollbutton']  = ' ls-move-btn ls-move-previous-btn ';
        $aClassAndAttributes['class']['navigatorcolr']        = '  ';
        $aClassAndAttributes['class']['navigatorcolrbutton']  = ' ls-move-btn ls-move-submit-btn ';
        $aClassAndAttributes['class']['completedquotaurl']    = ' url-wrapper url-wrapper-survey-quotaurl ';
        $aClassAndAttributes['class']['completedquotaurla']   = ' ls-endurl ls-quotaurl ';
        $aClassAndAttributes['class']['completedquotaurla']   = ' ls-endurl ls-quotaurl ';
        $aClassAndAttributes['class']['completedquotaurla']   = ' ls-endurl ls-quotaurl ';


        $aClassAndAttributes['attr']['navigatorcollbutton'] = '  type="submit" name="move" accesskey="p" ';
        $aClassAndAttributes['attr']['navigatorcolrbutton'] = '  type="submit" name="move" value="confirmquota" accesskey="l"   ';
        $aClassAndAttributes['attr']['completedwrapper'] =  $aClassAndAttributes['attr']['completedtext'] = $aClassAndAttributes['attr']['quotamessage']  = $aClassAndAttributes['attr']['navigator'] = $aClassAndAttributes['attr']['navigatorcoll'] = $aClassAndAttributes['attr']['navigatorcolr'] = $aClassAndAttributes['attr']['completedquotaurl'] ='';


        // Register
        $aClassAndAttributes['class']['register']                 = '  ';
        $aClassAndAttributes['class']['registerrow']              = '  ';
        $aClassAndAttributes['class']['registerrowjumbotrondiv']  = ' ';

        $aClassAndAttributes['class']['registerform']             = ' register-form  ';
        $aClassAndAttributes['class']['registerul']               = '  ';
        $aClassAndAttributes['class']['registerformcolrowlabel']  = ' ';
        $aClassAndAttributes['class']['registerformcol']          = '  ';
        $aClassAndAttributes['class']['registerformcolrow']       = ' ';
        $aClassAndAttributes['class']['registerformcolrowb']      = '  ';
        $aClassAndAttributes['class']['registerformcolrowc']      = '  ';
        $aClassAndAttributes['class']['registerformextras']       = '  ';
        $aClassAndAttributes['class']['registerformcaptcha']      = ' captcha-item ';
        $aClassAndAttributes['class']['registerformcolrowblabel'] = ' ';
        $aClassAndAttributes['class']['registerformcolrowclabel'] = ' ';
        $aClassAndAttributes['class']['registerformextraslabel']  = '  ';
        $aClassAndAttributes['class']['registerformcaptchalabel'] = '  ';
        $aClassAndAttributes['class']['registerformcaptchadivb']  = '  ';
        $aClassAndAttributes['class']['registerformcaptchadivc']  = '  captcha-widget ';
        $aClassAndAttributes['class']['registerformcaptchainput'] = '  ';
        $aClassAndAttributes['class']['registermandatoryinfo']    = '  ';
        $aClassAndAttributes['class']['registersave']             = ' ';
        $aClassAndAttributes['class']['registersavediv']          = '  ';
        $aClassAndAttributes['class']['registersavedivbutton']    = '  ';
        $aClassAndAttributes['class']['registerhead']             = '  ';




        $aClassAndAttributes['attr']['registerul']                = ' role="alert" ';
        $aClassAndAttributes['attr']['registerformcolrowblabel']  = ' for="register_lastname"  ';
        $aClassAndAttributes['attr']['registerformcolrowclabel']  = ' for="register_email"  ';
        $aClassAndAttributes['attr']['registerformcaptchalabel']  = ' for="loadsecurity"  ';
        $aClassAndAttributes['attr']['registerformcaptchainput']  = ' type="text" size="15" maxlength="15" id="loadsecurity" name="loadsecurity" value="" alt="" required ';
        $aClassAndAttributes['attr']['registermandatoryinfo']     = ' aria-hidden="true" ';
        $aClassAndAttributes['class']['registersavedivbutton']    = ' type="submit" id="savebutton" name="savesubmit" value="save"';

        $aClassAndAttributes['attr']['register']                  = $aClassAndAttributes['attr']['registerrow'] = $aClassAndAttributes['attr']['jumbotron'] = $aClassAndAttributes['attr']['registerrowjumbotrondiv'] = $aClassAndAttributes['attr']['registerulli'] = $aClassAndAttributes['class']['registerformcol'] = '';
        $aClassAndAttributes['attr']['registerformcolrow']        =  $aClassAndAttributes['attr']['registerformcolrowb'] = $aClassAndAttributes['attr']['registerformcolrowbdiv'] = $aClassAndAttributes['class']['registerformcolrowc'] = $aClassAndAttributes['class']['registerformcolrowcdiv'] = $aClassAndAttributes['attr']['registerformextras'] = '';
        $aClassAndAttributes['attr']['registerformcolrowcdiv']    = $aClassAndAttributes['attr']['registerformcaptcha'] = $aClassAndAttributes['attr']['registerformcaptchadiv'] = $aClassAndAttributes['attr']['registerformcaptchadivb'] = $aClassAndAttributes['attr']['registerformcaptchadivc'] = $aClassAndAttributes['attr']['registersave'] = '';
        $aClassAndAttributes['attr']['registersavediv'] = $aClassAndAttributes['attr']['registerhead'] = $aClassAndAttributes['attr']['registermessagea'] = $aClassAndAttributes['attr']['registermessageb'] = $aClassAndAttributes['attr']['registermessagec'] = '';

        // Warnings
        $aClassAndAttributes['class']['activealert']       = '  ';
        $aClassAndAttributes['class']['errorHtml']         = ' ls-questions-have-errors ';
        $aClassAndAttributes['class']['activealertbutton'] = '  ';
        $aClassAndAttributes['class']['errorHtmlbutton']   = ' ';
        $aClassAndAttributes['attr']['activealertbutton']  = ' type="button"  data-dismiss="alert" aria-label="Close" ';
        $aClassAndAttributes['attr']['errorHtmlbutton']    = ' type="button"  data-dismiss="alert" aria-label="Close" ';

        $aClassAndAttributes['attr']['activealert']  = 'role="alert"';

        // Required
        $aClassAndAttributes['class']['required']     = '  ';
        $aClassAndAttributes['class']['requiredspan'] = '  ';
        $aClassAndAttributes['attr']['required']      = ' aria-hidden="true" ';
        $aClassAndAttributes['class']['required']     = '';

        // Progress bar
        $aClassAndAttributes['class']['topcontainer'] = ' top-container ';
        $aClassAndAttributes['class']['topcontent']   = ' top-content ';
        $aClassAndAttributes['class']['progress']     = ' progress ';
        $aClassAndAttributes['class']['progressbar']  = ' progress-bar ';
        $aClassAndAttributes['attr']['progressbar']   = $aClassAndAttributes['attr']['topcontainer'] = $aClassAndAttributes['class']['topcontent'] = $aClassAndAttributes['attr']['progressbar']  =  $aClassAndAttributes['attr']['progress']  = ' ';

        // No JS alert
        $aClassAndAttributes['class']['nojs'] = ' ls-js-hidden warningjs ';
        $aClassAndAttributes['attr']['nojs']  = ' ';

        // NavBar
        $aClassAndAttributes['id']['navbar']            = ' navbar';
        $aClassAndAttributes['class']['navbar']         = ' navbar navbar-default';
        $aClassAndAttributes['class']['navbarheader']   = ' navbar-header ';
        $aClassAndAttributes['class']['navbartoggle']   = ' navbar-toggle collapsed ';
        $aClassAndAttributes['class']['navbarbrand']    = ' navbar-brand ';
        $aClassAndAttributes['class']['navbarcollapse'] = ' collapse navbar-collapse ';
        $aClassAndAttributes['class']['navbarlink']     = ' nav navbar-nav  navbar-action-link ';

        $aClassAndAttributes['attr']['navbartoggle']    = ' data-toggle="collapse" data-target="#navbar" aria-expanded="false" aria-controls="navbar" ';
        $aClassAndAttributes['attr']['navbar']  =  $aClassAndAttributes['attr']['navbarheader']  = $aClassAndAttributes['attr']['navbarbrand'] = $aClassAndAttributes['attr']['navbarcollapse']  = $aClassAndAttributes['attr']['navbarlink'] = '';

        // Language changer
        $aClassAndAttributes['class']['languagechanger'] = '  form-change-lang  ';
        $aClassAndAttributes['class']['formgroup']       = ' ';
        $aClassAndAttributes['class']['controllabel']    = ' ';
        $aClassAndAttributes['class']['aLCDWithForm']    = '  btn btn-default ls-js-hidden ';

        $aClassAndAttributes['attr']['languagechanger']  =  $aClassAndAttributes['attr']['formgroup']  = $aClassAndAttributes['attr']['controllabel'] = '';

        // Bootstrap Modal Alert
        $aClassAndAttributes['id']['alertmodal']           = 'bootstrap-alert-box-modal';
        $aClassAndAttributes['class']['alertmodal']        = ' modal fade ';
        $aClassAndAttributes['class']['modaldialog']       = ' modal-dialog ';
        $aClassAndAttributes['class']['modalcontent']      = ' modal-content ';
        $aClassAndAttributes['class']['modalheader']       = ' modal-header ';
        $aClassAndAttributes['class']['modalclosebutton']  = ' close ';
        $aClassAndAttributes['class']['modaltitle']        = ' modal-title h4 ';
        $aClassAndAttributes['class']['modalbody']         = ' modal-body ';
        $aClassAndAttributes['class']['modalfooter']       = ' modal-footer ';
        $aClassAndAttributes['class']['modalfooterlink']   = ' btn btn-default ';

        $aClassAndAttributes['attr']['modalheader']       = ' style="min-height:40px;" ';    // Todo: move to CSS
        $aClassAndAttributes['attr']['modalclosebutton']  = ' type="button" data-dismiss="modal" aria-hidden="true" ';
        $aClassAndAttributes['attr']['modalfooterlink']   = ' href="#" data-dismiss="modal" ';

        $aClassAndAttributes['attr']['alertmodal'] = $aClassAndAttributes['attr']['modaldialog'] = $aClassAndAttributes['attr']['modalcontent'] = $aClassAndAttributes['attr']['modaltitle'] = $aClassAndAttributes['attr']['modalbody'] = $aClassAndAttributes['attr']['modalfooter'] =  '';

        // Assessments
        $aClassAndAttributes['class']['assessmenttable']      = ' assessment-table ';
        $aClassAndAttributes['class']['assessmentstable']     = ' assessments ';
        $aClassAndAttributes['class']['assessmentstablet']    = ' assessments ';
        $aClassAndAttributes['class']['assessmentheading']    = ' assessment-heading ';
        $aClassAndAttributes['class']['assessmentscontainer'] = ' assessments-container ';

        $aClassAndAttributes['attr']['assessmentstablet'] = 'align="center"';

        $aClassAndAttributes['attr']['assessmenttable'] = $aClassAndAttributes['attr']['assessmentstabletr'] = $aClassAndAttributes['attr']['assessmentstablettr'] = $aClassAndAttributes['attr']['assessmentstabletth'] = $aClassAndAttributes['attr']['assessmentstablettd'] = $aClassAndAttributes['attr']['assessmentstableth'] = $aClassAndAttributes['attr']['assessmentstabletd'] = $aClassAndAttributes['attr']['assessmentstabletd'] = $aClassAndAttributes['attr']['assessmentheading'] = $aClassAndAttributes['attr']['assessmentscontainer'] = $aClassAndAttributes['attr']['assessmentstable'] = '';

        // Questions
        $aClassAndAttributes['class']['questioncontainer']       = ' question-container  ';
        $aClassAndAttributes['class']['questiontitlecontainer']  = ' question-title-container ';
        $aClassAndAttributes['class']['questionasterix']         = ' asterisk   ';
        $aClassAndAttributes['class']['questionasterixsmall']    = '  ';
        $aClassAndAttributes['class']['questionasterixspan']     = '  ';
        $aClassAndAttributes['class']['questionnumber']          = ' text-muted question-number ';
        $aClassAndAttributes['class']['questioncode']            = ' text-muted question-code ';
        $aClassAndAttributes['class']['questiontext']            = ' question-text ';
        $aClassAndAttributes['class']['lsquestiontext']          = ' ls-label-question ';
        $aClassAndAttributes['class']['questionvalidcontainer']  = ' question-valid-container  ';
        $aClassAndAttributes['class']['answercontainer']         = ' answer-container   ';
        $aClassAndAttributes['class']['helpcontainer']           = ' question-help-container ';
        $aClassAndAttributes['class']['lsquestionhelp']          = ' ls-questionhelp ';

        $aClassAndAttributes['attr']['questionasterixsmall'] = ' aria-hidden="true" ';

        $aClassAndAttributes['attr']['questioncontainer'] = $aClassAndAttributes['attr']['questiontitlecontainer'] = $aClassAndAttributes['attr']['questionasterix'] = $aClassAndAttributes['attr']['questionasterixspan'] = $aClassAndAttributes['attr']['questionnumber'] = $aClassAndAttributes['attr']['questioncode'] =  '';
        $aClassAndAttributes['attr']['questiontext'] = $aClassAndAttributes['attr']['lsquestiontext'] = $aClassAndAttributes['attr']['questionvalidcontainer'] = $aClassAndAttributes['attr']['answercontainer'] = $aClassAndAttributes['attr']['helpcontainer'] = '';

        // Question group
        $aClassAndAttributes['class']['groupcontainer'] = ' group-container ';
        $aClassAndAttributes['class']['groupoutercontainer'] = ' group-outer-container ';
        $aClassAndAttributes['class']['grouptitle']     = ' group-title  ';
        $aClassAndAttributes['class']['groupdesc']      = ' group-description ';

        $aClassAndAttributes['attr']['questiongroup']  = $aClassAndAttributes['attr']['groupcontainer'] = $aClassAndAttributes['attr']['groupcontainer'] = $aClassAndAttributes['attr']['groupdesc'] = '';

        // Privacy
        $aClassAndAttributes['class']['privacycontainer'] = ' privacy ';
        $aClassAndAttributes['class']['privacycol']       = ' ';
        $aClassAndAttributes['class']['privacyhead']      = ' ';
        $aClassAndAttributes['class']['privacybody']      = ' ls-privacy-body ';

        $aClassAndAttributes['attr']['privacycontainer'] = $aClassAndAttributes['attr']['privacycol'] = $aClassAndAttributes['attr']['privacyhead'] = $aClassAndAttributes['attr']['privacybody'] = '';

        // Clearall Links
        $aClassAndAttributes['class']['clearalllinks'] = ' ls-no-js-hidden ';
        $aClassAndAttributes['class']['clearalllink']  = ' ls-link-action ls-link-clearall ';

        $aClassAndAttributes['attr']['clearalllinks']  = $aClassAndAttributes['attr']['clearalllink'] = ' ';

        // Language changer
        $aClassAndAttributes['id']['lctdropdown']    = 'langs-container';

        $aClassAndAttributes['class']['lctli']          = ' ls-no-js-hidden form-change-lang ';
        $aClassAndAttributes['class']['lctla']          = ' ';
        $aClassAndAttributes['class']['lctspan']        = ' ';
        $aClassAndAttributes['class']['lctdropdown']    = ' language_change_container ';
        $aClassAndAttributes['class']['lctdropdownli']  = 'index-item ';
        $aClassAndAttributes['class']['lctdropdownlia'] = 'ls-language-link ';

        $aClassAndAttributes['attr']['lctla']       = ' data-toggle="dropdown" href="#" role="button" aria-haspopup="true" aria-expanded="false" ';
        $aClassAndAttributes['attr']['lctdropdown'] = ' style="overflow: scroll" ';

        $aClassAndAttributes['attr']['lctli'] = $aClassAndAttributes['attr']['lctspan'] = $aClassAndAttributes['attr']['lctdropdownli'] = $aClassAndAttributes['attr']['lctdropdownlia'] = ' ';
        $aClassAndAttributes['attr']['navigatorcontainer'] = $aClassAndAttributes['attr']['navigatorbuttonl'] = $aClassAndAttributes['attr']['loadsavecontainer'] = $aClassAndAttributes['attr']['loadsavecol']  = '';

        // Navigator
        $aClassAndAttributes['id']['navigatorcontainer'] = 'navigator-container';

        $aClassAndAttributes['class']['navigatorcontainer']    = '   ';
        $aClassAndAttributes['class']['navigatorbuttonl']      = '  ';
        $aClassAndAttributes['class']['navigatorbuttonprev']   = ' ls-move-btn ls-move-previous-btn ';
        $aClassAndAttributes['class']['navigatorbuttonr']      = '  ';
        $aClassAndAttributes['class']['navigatorbuttonsubmit'] = ' ls-move-btn ls-move-submit-btn  ';
        $aClassAndAttributes['class']['navigatorbuttonnext']   = ' ls-move-btn ls-move-next-btn ls-move-submit-btn  ';
        $aClassAndAttributes['class']['loadsavecontainer']     = '  ';
        $aClassAndAttributes['class']['loadsaveccol']          = ' save-clearall-wrapper ';
        $aClassAndAttributes['class']['loadbutton']            = ' ls-saveaction ls-loadall ';
        $aClassAndAttributes['class']['savebutton']            = ' ls-saveaction ls-loadall ';

        $aClassAndAttributes['attr']['navigatorbuttonprev']   = ' type="submit" value="moveprev" name="move" accesskey="p" accesskey="n"';
        $aClassAndAttributes['attr']['navigatorbuttonsubmit'] = ' type="submit" value="movesubmit" name="move" accesskey="l" ';
        $aClassAndAttributes['attr']['navigatorbuttonnext']   = ' type="submit" value="movenext" name="move"  ';
        $aClassAndAttributes['attr']['loadbutton']            = ' type="submit" value="loadall" name="loadall" accesskey="L"';
        $aClassAndAttributes['attr']['savebutton']            = ' type="submit" value="saveall" name="saveall" accesskey="s" ';

        // Index Menu
        $aClassAndAttributes['class']['indexmenugli']     = ' ls-index-menu ls-no-js-hidden  ';
        $aClassAndAttributes['class']['indexmenuglia']    = '   ';
        $aClassAndAttributes['class']['indexmenugspan']   = '  ';
        $aClassAndAttributes['class']['indexmenusgul']    = '  ';
        $aClassAndAttributes['class']['indexmenusli']     = ' ls-index-menu ls-no-js-hidden  ';
        $aClassAndAttributes['class']['indexmenuslia']    = '   ';
        $aClassAndAttributes['class']['indexmenusspan']   = '  ';
        $aClassAndAttributes['class']['indexmenussul']    = '  ';
        $aClassAndAttributes['class']['indexmenusddh']    = '  ';
        $aClassAndAttributes['class']['indexmenusddspan'] = '  ';
        $aClassAndAttributes['class']['indexmenusddul']   = '  dropdown-sub-menu ';

        $aClassAndAttributes['attr']['indexmenuglia']          = ' data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false"';
        $aClassAndAttributes['attr']['indexmenuslia']          = ' data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false"';

        $aClassAndAttributes['attr']['indexmenugli']  = $aClassAndAttributes['attr']['indexmenugspan'] = $aClassAndAttributes['attr']['indexmenusgul'] = $aClassAndAttributes['attr']['indexmenusli'] = $aClassAndAttributes['attr']['indexmenusspan'] = $aClassAndAttributes['attr']['indexmenussul'] = '';
        $aClassAndAttributes['attr']['indexmenusddh'] = $aClassAndAttributes['attr']['indexmenusddspan']  = $aClassAndAttributes['attr']['indexmenusddul'] = $aClassAndAttributes['attr']['indexmenussli'] = $aClassAndAttributes['attr']['indexmenusgli'] = '';

        // Preview submit
        $aClassAndAttributes['class']['previewsubmit']      = ' completed-wrapper  ';
        $aClassAndAttributes['class']['previewsubmittext']  = ' completed-text  ';
        $aClassAndAttributes['class']['submitwrapper']      = ' completed-wrapper  ';
        $aClassAndAttributes['class']['submitwrappertext']  = ' completed-text  ';
        $aClassAndAttributes['class']['submitwrapperdiva']  = ' url-wrapper url-wrapper-survey-print ';
        $aClassAndAttributes['class']['submitwrapperdivaa'] = ' ls-print ';
        $aClassAndAttributes['class']['submitwrapperdivb']  = ' url-wrapper url-wrapper-survey-print ';
        $aClassAndAttributes['class']['submitwrapperdivba']  = ' ls-print ';

        $aClassAndAttributes['attr']['previewsubmit']     = $aClassAndAttributes['attr']['previewsubmittext'] = $aClassAndAttributes['attr']['previewsubmitstrong'] = $aClassAndAttributes['attr']['submitwrapper'] = $aClassAndAttributes['attr']['submitwrappertext'] = $aClassAndAttributes['attr']['submitwrapperdiv'] = '';
        $aClassAndAttributes['attr']['submitwrapperdiva'] = $aClassAndAttributes['attr']['submitwrapperdivaa'] = $aClassAndAttributes['attr']['submitwrapperdivb'] = $aClassAndAttributes['attr']['submitwrapperdivba'] = '';

        // Survey list
        $aClassAndAttributes['id']['surveylistrow']          = 'surveys-list-container';
        $aClassAndAttributes['id']['surveylistrowjumbotron'] = 'surveys-list-jumbotron';
        $aClassAndAttributes['id']['surveylistfooter']       = 'surveyListFooter';

        $aClassAndAttributes['class']['surveylistrow']             = '  ';
        $aClassAndAttributes['class']['surveylistrowdiva']         = '  survey-list-heading ';
        $aClassAndAttributes['class']['surveylistrowdivadiv']      = '  ';
        $aClassAndAttributes['class']['surveylistrowdivb']         = '  survey-list ';
        $aClassAndAttributes['class']['surveylistrowdivbdiv']      = ' surveys-list-container ';
        $aClassAndAttributes['class']['surveylistrowdivbdivul']    = ' surveys-list ';
        $aClassAndAttributes['class']['surveylistrowdivbdivulli']  = '  ';
        $aClassAndAttributes['class']['surveylistrowdivbdivullia'] = ' surveytitle  ';
        $aClassAndAttributes['class']['surveylistrowdivc']         = ' survey-contact ';
        $aClassAndAttributes['class']['surveylistfooter']          = ' footer ';
        $aClassAndAttributes['class']['surveylistfootercont']      = '  ';

        $aClassAndAttributes['attr']['surveylistfootercontpaa']      = ' href="http://www.limesurvey.org"  target="_blank" ';
        $aClassAndAttributes['attr']['surveylistfootercontpaa']      = ' href="http://www.limesurvey.org"  target="_blank" ';



        $aClassAndAttributes['attr']['surveylistrow'] = $aClassAndAttributes['attr']['surveylistrowjumbotron'] = $aClassAndAttributes['attr']['surveylistrowdiva'] = $aClassAndAttributes['attr']['surveylistrowdivadiv'] = $aClassAndAttributes['attr']['surveylistrowdivb'] = $aClassAndAttributes['attr']['surveylistrowdivbdivul'] = '';
        $aClassAndAttributes['attr']['surveylistrowdivbdivulli'] = $aClassAndAttributes['attr']['surveylistrowdivc'] = $aClassAndAttributes['attr']['surveylistfooter'] = $aClassAndAttributes['attr']['surveylistfootercont'] = $aClassAndAttributes['class']['surveylistfootercontp'] = '';

        // Save/Load links
        $aClassAndAttributes['class']['loadlinksli']  = ' ls-no-js-hidden ';
        $aClassAndAttributes['class']['loadlinkslia'] = ' ls-link-action ls-link-loadall ';
        $aClassAndAttributes['class']['savelinksli']  = ' ls-no-js-hidden ';
        $aClassAndAttributes['class']['savelinkslia'] = 'ls-link-action ls-link-saveall';

        $aClassAndAttributes['attr']['loadlinksli']     = $aClassAndAttributes['attr']['savelinksli'] = $aClassAndAttributes['class']['savelinkslia'] = '';

        // Here you can add metas from core
        $aClassAndAttributes['metas']    = '    ';

        // Maybe add a plugin event here?

        return $aClassAndAttributes;

    }

    public function __toString()
    {
        $s = '';
        foreach($this as $k => $v){
            $s .= " <strong> $k : </strong>  $v  <br/>";
         }

         $aProp = get_object_vars($this);
         foreach ( $aProp as $k => $v) {
             $s .= " <strong> $k : </strong>  $v  <br/>";

         }

         return $s;
    }


    public static function uninstall($templatename )
    {
        if (Permission::model()->hasGlobalPermission('templates','delete'))
        {
            $oTemplate                = Template::model()->findByAttributes(array('name' => $templatename));
            if( $oTemplate->delete()){
                $oTemplateConfig      = TemplateConfiguration::model()->findByAttributes(array('template_name' => $templatename));
                return TemplateConfiguration::model()->deleteAll('template_name=:templateName', array(':templateName' => $templatename) );
            }
        }
        return false;
    }

    /**
     * Create a new entry in {{templates}} and {{template_configuration}} table using the template manifest
     * @param string $sTemplateName the name of the template to import
     * @param array $aDatas
     * @return boolean true on success | exception
     * @throws Exception, InvalidArgumentException
     */
    public static function importManifest($sTemplateName, $aDatas)
    {
        if (empty($aDatas)) {
            throw new InvalidArgumentException('$aDatas cannot be empty');
        }

        $oNewTemplate                   = new Template;
        $oNewTemplate->name             = $sTemplateName;
        $oNewTemplate->folder           = $sTemplateName;
        $oNewTemplate->title            = $sTemplateName;  // For now, when created via template editor => name == folder == title
        $oNewTemplate->creation_date    = date("Y-m-d H:i:s");
        $oNewTemplate->author           = Yii::app()->user->name;
        $oNewTemplate->author_email     = ''; // privacy
        $oNewTemplate->author_url       = ''; // privacy
        $oNewTemplate->api_version      = $aDatas['api_version'];
        $oNewTemplate->view_folder      = $aDatas['view_folder'];
        $oNewTemplate->files_folder     = $aDatas['files_folder'];
        $oNewTemplate->owner_id         = Yii::app()->user->id;
        $oNewTemplate->extends          = $aDatas['extends'];
        
        if ($oNewTemplate->save()){
            $oNewTemplateConfiguration                   = new TemplateConfiguration;
            $oNewTemplateConfiguration->template_name    = $sTemplateName;
            $oNewTemplateConfiguration->template_name    = $sTemplateName;
            $oNewTemplateConfiguration->options          = json_encode($aDatas['aOptions']);
            $oNewTemplateConfiguration->packages_to_load = json_encode($aDatas['packages_to_load']);


            if ($oNewTemplateConfiguration->save()){
                return true;
            }else{
                throw new Exception($oNewTemplateConfiguration->getErrors());
            }
        }else{
            throw new Exception($oNewTemplate->getErrors());
        }
    }

    // TODO: try to refactore most of those methods in TemplateConfiguration and TemplateManifest so we can define their body here.
    // It will consist in adding private methods to get the values of variables... See what has been done for createTemplatePackage
    // Then, the lonely differences between TemplateManifest and TemplateConfiguration should be how to retreive and format the data
    // Note: signature are already the same

    public static function rename($sOldName,$sNewName){}
    public function prepareTemplateRendering($sTemplateName='', $iSurveyId='', $bUseMagicInherit=true){}
    public function addFileReplacement($sFile, $sType){}

    protected function getTemplateForPath($oRTemplate, $sPath ) {}

    /**
     * @param string $sType
     */
    protected function getFilesToLoad($oTemplate, $sType){}

    /**
     * @param string $sType
     */
    protected function changeMotherConfiguration( $sType, $aSettings ){}

    /**
     * @param string $sType
     */
    protected function getFrameworkAssetsToReplace( $sType, $bInlcudeRemove = false){}

    /**
     * @param string $sType
     */
    protected function getFrameworkAssetsReplacement($sType){}
    protected function removeFileFromPackage( $sPackageName, $sType, $aSettings ){}
    protected function setMotherTemplates(){}
    protected function setThisTemplate(){}
 }
