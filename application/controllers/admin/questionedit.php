<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/*
* LimeSurvey
* Copyright (C) 2007-2011 The LimeSurvey Project Team / Carsten Schmitz
* All rights reserved.
* License: GNU/GPL License v2 or later, see LICENSE.php
* LimeSurvey is free software. This version may have been modified pursuant
* to the GNU General Public License, and as distributed it includes or
* is derivative of works licensed under the GNU General Public License or
* other free or open source software licenses.
* See COPYRIGHT.php for copyright notices and details.
*
*/

/**
 * Class questionedit
 * question
 * @package LimeSurvey
 * @author
 * @copyright 2011
 * @access public
 */
class questionedit extends Survey_Common_Action
{
    /**
     * @param integer $surveyid
     * @param integer $gid
     * @param integer $qid
     * @param string $landOnSideMenuTab
     * @throws CException
     * @throws CHttpException
     */
    public function view($surveyid, $gid = null, $qid = null, $landOnSideMenuTab = '')
    {
        $aData = array();
        $iSurveyID = (int) $surveyid;
        $oSurvey = Survey::model()->findByPk($iSurveyID);
        $gid = $gid ?? $oSurvey->groups[0]->gid;
        $oQuestion = $this->_getQuestionObject($qid, null, $gid);
        $oTemplateConfiguration = TemplateConfiguration::getInstance($oSurvey->template, null, $iSurveyID);
        App()->getClientScript()->registerPackage('questioneditor');
        App()->getClientScript()->registerPackage('ace');
        $qrrow = $oQuestion->attributes;
        $baselang = $oSurvey->language;

        $condarray = ($oQuestion->qid != null)
            ? getQuestDepsForConditions(
                $iSurveyID,
                "all",
                "all",
                $oQuestion->qid,
                "by-targqid",
                "outsidegroup"
            )
            : [];

        $aData['display']['menu_bars']['gid_action'] = 'viewquestion';
        $aData['questionbar']['buttons']['view'] = true;

        // Last question visited : By user (only one by user)
        $setting_entry = 'last_question_'.App()->user->getId();
        SettingGlobal::setSetting($setting_entry, $oQuestion->qid);

        // we need to set the sid for this question
        $setting_entry = 'last_question_sid_'.App()->user->getId();
        SettingGlobal::setSetting($setting_entry, $iSurveyID);

        // we need to set the gid for this question
        $setting_entry = 'last_question_gid_'.App()->user->getId();
        SettingGlobal::setSetting($setting_entry, $gid);

        // Last question for this survey (only one by survey, many by user)
        $setting_entry = 'last_question_'.App()->user->getId().'_'.$iSurveyID;
        SettingGlobal::setSetting($setting_entry, $oQuestion->qid);

        // we need to set the gid for this question
        $setting_entry = 'last_question_'.App()->user->getId().'_'.$iSurveyID.'_gid';
        SettingGlobal::setSetting($setting_entry, $gid);

        ///////////
        // combine aData
        $aData['surveyid'] = $iSurveyID;
        $aData['oSurvey'] = $oSurvey;
        $aData['aQuestionTypeList'] = QuestionTheme::findAllQuestionMetaDataForSelector();
        $aData['selectedQuestion'] = QuestionTheme::findQuestionMetaData($oQuestion->type);
        $aData['gid'] = $gid;
        $aData['qid'] = $oQuestion->qid;
        $aData['activated'] = $oSurvey->active;
        $aData['oQuestion'] = $oQuestion;
        $aData['languagelist'] = $oSurvey->allLanguages;
        $aData['qshowstyle'] = '';
        $aData['qrrow'] = $qrrow;
        $aData['baselang'] = $baselang;

        $aData['sImageURL'] = App()->getConfig('adminimageurl');
        $aData['iIconSize'] = App()->getConfig('adminthemeiconsize');
        $aData['display']['menu_bars']['qid_action'] = 'editquestion';
        $aData['display']['menu_bars']['gid_action'] = 'viewquestion';
        $aData['action'] = 'editquestion';
        $aData['editing'] = true;

        $aData['title_bar']['title'] = $oSurvey->currentLanguageSettings->surveyls_title.
            " (".gT("ID").":".$iSurveyID.")";
        $aData['surveyIsActive'] = $oSurvey->active !== 'N';
        $aData['activated'] = $oSurvey->active;
        $aData['jsData'] = [
            'surveyid' => $iSurveyID,
            'surveyObject' => $oSurvey->attributes,
            'gid' => $gid,
            'qid' => $oQuestion->qid,
            'startType' => $oQuestion->type,
            'baseSQACode' => [
                'answeroptions' => SettingsUser::getUserSettingValue('answeroptionprefix', App()->user->id),
                'subquestions' => SettingsUser::getUserSettingValue('subquestionprefix', App()->user->id),
            ],
            'startInEditView' => SettingsUser::getUserSettingValue('noViewMode', App()->user->id) == '1',
            'connectorBaseUrl' => $this->getController()->createUrl(
                'admin/questioneditor',
                ['sid' => $iSurveyID, 'gid' => $gid, 'sa' => '']
            ),
            'i10N' => [
                'Create new Question' => gT('Create new Question'),
                'General Settings' => gT("General Settings"),
                'Code' => gT('Code'),
                'Text elements' => gT('Text elements'),
                'Question type' => gT('Question type'),
                'Question' => gT('Question'),
                'Help' => gT('Help'),
                'subquestions' => gT('Subquestions'),
                'answeroptions' => gT('Answer options'),
                'Quick add' => gT('Quick add'),
                'Predefined label sets' => gT('Predefined label sets'),
                'Save as label set' => gT('Save as label set'),
                'More languages' => gT('More languages'),
                'Add subquestion' => gT('Add subquestion'),
                'Reset' => gT('Reset'),
                'Save' => gT('Save'),
                'Some example subquestion' => gT('Some example subquestion'),
                'Delete' => gT('Delete'),
                'Open editor' => gT('Open editor'),
                'Duplicate' => gT('Duplicate'),
                'No preview available' => gT('No preview available'),
                'Editor' => gT('Editor'),
                'Quick edit' => gT('Quick edit'),
                'Cancel' => gT('Cancel'),
                'Replace' => gT('Replace'),
                'Add' => gT('Add'),
                'Select delimiter' => gT('Select delimiter'),
                'Semicolon' => gT('Semicolon'),
                'Comma' => gT('Comma'),
                'Tab' => gT('Tab'),
                'New rows' => gT('New rows'),
                'Scale' => gT('Scale'),
                'Save and Close' => gT('Save and Close'),
                'Script' => gT('Script'),
                '__SCRIPTHELP' => gT(
                    "This optional script field will be wrapped, so that the script is correctly executed
                     after the question is on the screen. If you do not have the correct permissions, 
                     this will be ignored"
                ),
                "noCodeWarning" => gT(
                    "Please put in a code. Only letters and numbers are allowed. For example [Question1]"
                ),
            ]
        ];
        
        $aData['topBar']['type'] = 'question';

        $aData['topBar']['importquestion'] = true;
        $aData['topBar']['showSaveButton'] = true;
        $aData['topBar']['savebuttonform'] = 'frmeditgroup';
        $aData['topBar']['closebuttonurl'] = '/admin/survey/sa/listquestions/surveyid/'.$iSurveyID; // Close button

        if ($landOnSideMenuTab !== '') {
            $aData['sidemenu']['landOnSideMenuTab'] = $landOnSideMenuTab;
        }
        $this->_renderWrappedTemplate('survey/Question2', 'view', $aData);
    }

    /****
    **** A lot of getter function regarding functionalities and views.
    **** All called via ajax
    ****/

    /** @todo document me
     * @param $iSurveyId
     */
    public function getPossibleLanguages($iSurveyId)
    {
        $iSurveyId = (int) $iSurveyId;
        $aLanguages = Survey::model()->findByPk($iSurveyId)->allLanguages;
        $this->renderJSON($aLanguages);
    }

    /**
     * Saves question data.
     * @param string $sid Survey ID
     * @throws CException
     * @throws Exception
     */
    public function saveQuestionData($sid)
    {
        $questionData = App()->request->getPost('questionData', []);
        $iSurveyId = (int) $sid;
        $oQuestion = Question::model()->findByPk($questionData['question']['qid']);

        if ($oQuestion != null) {
            $oQuestion = $this->_editQuestion($oQuestion, $questionData['question']);

            $setApplied = [];
            $setApplied['generalSettings'] = $this->_unparseAndSetGeneralOptions(
                $oQuestion,
                $questionData['generalSettings']
            );
            $setApplied['advancedSettings'] = $this->_unparseAndSetAdvancedOptions(
                $oQuestion,
                $questionData['advancedSettings']
            );
            $setApplied['questionI10N'] = $this->_applyI10N($oQuestion, $questionData['questionI10N']);

            // save advanced attributes default values for given question type
            if (array_key_exists('save_as_default', $questionData['generalSettings']) &&
                $questionData['generalSettings']['save_as_default']['formElementValue'] == 'Y') {
                SettingsUser::setUserSetting(
                    'question_default_values_' .
                    $questionData['question']['type'],
                    ls_json_encode($questionData['advancedSettings'])
                );
            } elseif (array_key_exists('clear_default', $questionData['generalSettings']) &&
                $questionData['generalSettings']['clear_default']['formElementValue'] == 'Y') {
                SettingsUser::deleteUserSetting('question_default_values_' . $questionData['question']['type'], '');
            }

            if (isset($questionData['scaledSubquestions'])) {
                $setApplied['scaledSubquestions'] = $this->_storeSubquestions(
                    $oQuestion,
                    $questionData['scaledSubquestions']
                );
            }

            if (isset($questionData['scaledAnswerOptions'])) {
                $setApplied['scaledAnswerOptions'] = $this->_storeAnswerOptions(
                    $oQuestion,
                    $questionData['scaledAnswerOptions']
                );
            }
            $oNewQuestion = Question::model()->findByPk($oQuestion->qid);
            $aCompiledQuestionData = $this->_getCompiledQuestionData($oNewQuestion);
            $aQuestionAttributeData = $this->getQuestionAttributeData($oQuestion->qid, $oQuestion->gid, true);
            $aQuestionGeneralOptions = $this->getGeneralOptions(
                $oQuestion->qid,
                null,
                $oQuestion->gid,
                true,
                $aQuestionAttributeData['question_template']
            );
            $aAdvancedOptions = $this->getAdvancedOptions($oQuestion->qid, null, true);
            $this->renderJSON([
                'success' => array_reduce($setApplied, function ($coll, $it) {
                    return $coll && $it;
                }, true),
                'message' => gT('Question successfully stored'),
                'successDetail' => $setApplied,
                'questionId' => $oQuestion->qid,
                'redirect' => $this->getController()->createUrl(
                    'admin/questioneditor/sa/view/',
                    ['surveyid' => $iSurveyId, 'gid' => $oQuestion->gid, 'qid' => $oQuestion->qid]
                ),
                'newQuestionDetails' => [
                    "question" => $aCompiledQuestionData['question'],
                    "scaledSubquestions" => $aCompiledQuestionData['subquestions'],
                    "scaledAnswerOptions" => $aCompiledQuestionData['answerOptions'],
                    "questionI10N" => $aCompiledQuestionData['i10n'],
                    "questionAttributes" => $aQuestionAttributeData,
                    "generalSettings" => $aQuestionGeneralOptions,
                    "advancedSettings" => $aAdvancedOptions
                ],
                'transfer' => $questionData,
            ]);
            App()->close();
        } else {
            $oQuestion = $this->_newQuestion($questionData['question']);
            $errors = $oQuestion->getErrors();
            $error  = "Object creation failed, couldn't save.\n ".$errors['title'][0];
            $json = json_encode(['error'=> [
                'message' => $error,
                'code' => 500,
            ]]);
            header('500 Internal Server Error', true, 500);
            echo $json;
        }
    }

    /**
     * @param null $iQuestionId
     * @param null $type
     * @param null $gid
     * @param string $question_template
     * @throws CException
     * @todo document me
     */
    public function reloadQuestionData($iQuestionId = null, $type = null, $gid = null, $question_template = 'core')
    {
        $iQuestionId = (int) $iQuestionId;
        $oQuestion = $this->_getQuestionObject($iQuestionId, $type, $gid);

        $aCompiledQuestionData = $this->_getCompiledQuestionData($oQuestion);
        $aQuestionGeneralOptions = $this->getGeneralOptions(
            $oQuestion->qid,
            $type,
            $oQuestion->gid,
            true,
            $question_template
        );
        $aAdvancedOptions = $this->getAdvancedOptions($oQuestion->qid, $type, true, $question_template);

        $aLanguages = [];
        $aAllLanguages = getLanguageData(false, App()->session['adminlang']);
        $aSurveyLanguages = $oQuestion->survey->getAllLanguages();

        array_walk($aSurveyLanguages, function ($lngString) use (&$aLanguages, $aAllLanguages) {
            $aLanguages[$lngString] = $aAllLanguages[$lngString]['description'];
        });

        $this->renderJSON(
            array_merge(
                $aCompiledQuestionData,
                [
                    'languages' => $aLanguages,
                    'mainLanguage' => $oQuestion->survey->language,
                    'generalSettings' => $aQuestionGeneralOptions,
                    'advancedSettings' => $aAdvancedOptions,
                    'questiongroup' => $oQuestion->group->attributes
                ]
            )
        );
    }

    /**
     * @param null $iQuestionId
     * @param null $gid
     * @param null $type
     * @throws CException
     * @todo document me.
     *
     */
    public function getQuestionData($iQuestionId = null, $gid = null, $type = null)
    {
        $iQuestionId = (int) $iQuestionId;
        $oQuestion = $this->_getQuestionObject($iQuestionId, $type, $gid);

        $aQuestionInformationObject = $this->_getCompiledQuestionData($oQuestion);

        $aLanguages = [];
        $aAllLanguages = getLanguageData(false, App()->session['adminlang']);
        $aSurveyLanguages = $oQuestion->survey->getAllLanguages();
        array_walk($aSurveyLanguages, function ($lngString) use (&$aLanguages, $aAllLanguages) {
            $aLanguages[$lngString] = $aAllLanguages[$lngString]['description'];
        });

        $this->renderJSON(array_merge($aQuestionInformationObject, [
            'languages' => $aLanguages,
            'mainLanguage' => $oQuestion->survey->language
        ]));
    }

    /**
     * @param null $iQuestionId
     * @throws CException
     * @todo document me.
     */
    public function getQuestionPermissions($iQuestionId = null)
    {
        $iQuestionId = (int) $iQuestionId;
        $oQuestion = $this->_getQuestionObject($iQuestionId);

        $aPermissions = [
            "read" => Permission::model()->hasSurveyPermission($oQuestion->sid, 'survey', 'read'),
            "update" => Permission::model()->hasSurveyPermission($oQuestion->sid, 'survey', 'update'),
            "editorpreset" => App()->session['htmleditormode'],
            "script" => SettingsUser::getUserSetting(
                'showScriptEdit',
                App()->user->id
            ) && Permission::model()->hasSurveyPermission(
                $oQuestion->sid,
                'survey',
                'update'
            ),
        ];

        $this->renderJSON($aPermissions);
    }

    /**
     * @todo document me
     * @todo missing return statement (php warning)
     * @param null $iQuestionId
     * @param null $gid
     * @param bool $returnArray
     * @return array|bool
     * @throws CException
     */
    public function getQuestionAttributeData($iQuestionId = null, $gid = null, $returnArray = false)
    {
        $iQuestionId = (int) $iQuestionId;
        $aQuestionAttributes = QuestionAttribute::model()->getQuestionAttributes($iQuestionId);
        if ($returnArray === true) {
            return $aQuestionAttributes;
        }
        $this->renderJSON($aQuestionAttributes);
    }

    /**
     * @todo document me
     */
    public function getQuestionTypeList()
    {
        $this->renderJSON(QuestionType::modelsAttributes());
    }

    /**
     * @todo document me
     * @param $sQuestionType
     */
    public function getQuestionTypeInformation($sQuestionType)
    {
        $aTypeInformations = QuestionType::modelsAttributes();
        $aQuestionTypeInformation = $aTypeInformations[$sQuestionType];

        $this->renderJSON($aQuestionTypeInformation);
    }

    /**
     * @todo document me
     * @todo missing return statement (php warning)
     * @param null $iQuestionId
     * @param null $sQuestionType
     * @param null $gid
     * @param bool $returnArray
     * @param string $question_template
     * @return array
     * @throws CException
     */
    public function getGeneralOptions($iQuestionId = null, $sQuestionType = null, $gid = null, $returnArray = false, $question_template = 'core')
    {
        $oQuestion = $this->_getQuestionObject($iQuestionId, $sQuestionType, $gid);
        $aGeneralOptionsArray = $oQuestion->getDataSetObject()->getGeneralSettingsArray(
            $oQuestion->qid,
            $sQuestionType,
            null,
            $question_template
        );

        if ($returnArray === true) {
            return $aGeneralOptionsArray;
        }

        $this->renderJSON($aGeneralOptionsArray);
    }

    /**
     * @todo missing return statement (php warning)
     * @todo document me
     * @param null $iQuestionId
     * @param null $sQuestionType
     * @param bool $returnArray
     * @param string $question_template
     * @return array
     * @throws CException
     */
    public function getAdvancedOptions($iQuestionId = null, $sQuestionType = null, $returnArray = false, $question_template = 'core')
    {
        $oQuestion = $this->_getQuestionObject($iQuestionId, $sQuestionType);
        $aAdvancedOptionsArray = $oQuestion->getDataSetObject()->getAdvancedOptions(
            $oQuestion->qid,
            $sQuestionType,
            null,
            $question_template
        );
        if ($returnArray === true) {
            return $aAdvancedOptionsArray;
        }
        
        $this->renderJSON([
            'advancedSettings' => $aAdvancedOptionsArray,
            'questionTypeDefinition' => $oQuestion->questionType,
        ]);
    }

    /**
     * Live preview rendering
     *
     * @param int $iQuestionId
     * @param string $sLanguage
     * @param boolean $root
     *
     * @return void
     * @throws CException
     * @throws Throwable
     * @throws Twig_Error_Loader
     * @throws Twig_Error_Syntax
     * @throws WrongTemplateVersionException
     */
    public function getRenderedPreview($iQuestionId, $sLanguage, $root = false)
    {
        if ($iQuestionId == null) {
            echo "<h3>No Preview available</h3>";
            return;
        }
        $root = (bool) $root;

        $changedText = App()->request->getPost('changedText', []);
        $changedType = App()->request->getPost('changedType', null);
        $oQuestion = Question::model()->findByPk($iQuestionId);

        $changedType = $changedType == null ? $oQuestion->type : $changedType;

        if ($changedText !== []) {
            App()->session['edit_'.$iQuestionId.'_changedText'] = $changedText;
        } else {
            $changedText = isset(App()->session['edit_'.$iQuestionId.'_changedText'])
                ? App()->session['edit_'.$iQuestionId.'_changedText']
                : [];
        }

        $aFieldArray = [
        //*  0 => string qid
            $oQuestion->qid,
        //*  1 => string sgqa | This should be working because it is only about parent questions here!
            "{$oQuestion->sid}X{$oQuestion->gid}X{$oQuestion->qid}",
        //*  2 => string questioncode
            $oQuestion->title,
        //*  3 => string question | technically never used in the new renderers and totally unessecary therefor empty
            "",
        //*  4 => string type
            $oQuestion->type,
        //*  5 => string gid
            $oQuestion->gid,
        //*  6 => string mandatory,
            ($oQuestion->mandatory == 'Y'),
        ];
        Yii::import('application.helpers.qanda_helper', true);
        setNoAnswerMode(['shownoanswer' => $oQuestion->survey->shownoanswer ]);

        $oQuestionRenderer = $oQuestion->getRenderererObject($aFieldArray, $changedType);
        $aRendered =  $oQuestionRenderer->render('applyCkToFields');
        $aSurveyInfo = $oQuestion->survey->attributes;
        $aQuestion = array_merge(
            $oQuestion->attributes,
            QuestionAttribute::model()->getQuestionAttributes($iQuestionId),
            ['answer' => $aRendered[0]],
            [
                'number' => $oQuestion->question_order,
                'code' => $oQuestion->title,
                'text' => isset($changedText['question'])
                    ? $changedText['question']
                    : $oQuestion->questionL10ns[$sLanguage]->question,
                'help' => [
                    'show' => true,
                    'text' => (isset($changedText['help'])
                        ? $changedText['help']
                        : $oQuestion->questionL10ns[$sLanguage]->help)
                ],
            ]
        );
        Template::resetInstance();
        $oTemplate = Template::getInstance($oQuestion->survey->template);
        App()->twigRenderer->renderTemplateForQuestionEditPreview(
            '/subviews/survey/question_container.twig',
            ['aSurveyInfo' => $aSurveyInfo, 'aQuestion' => $aQuestion, 'session' => $_SESSION],
            $root
        );

        return;
    }

    /**
     * @param null $qid
     * @return mixed
     * @throws CException
     * @todo document me
     */
    public function getQuestionTopbar($qid = null)
    {
        $oQuestion = $this->_getQuestionObject($qid);
        // TODO: deprecated
        $qtypes    = Question::typeList();
        $qrrow     = $oQuestion->attributes;
        $ownsSaveButton = true;
        $ownsSaveAndCloseButton = true;
        $ownsCloseButton = true;
    
        return App()->getController()->renderPartial(
            '/admin/survey/topbar/question_topbar',
            array(
                'oSurvey' => $oQuestion->survey,
                'sid' => $oQuestion->sid,
                'gid' => $oQuestion->gid,
                'qid' => $oQuestion->qid,
                'qrrow'  => $qrrow,
                'qtypes' => $qtypes,
                'ownsSaveButton'         => $ownsSaveButton,
                'ownsSaveAndCloseButton' => $ownsSaveAndCloseButton,
                'ownsCloseButton'        => $ownsCloseButton,
            ),
            false,
            false
        );
    }

    /**
     * @todo document me.
     * @param null $iQuestionId
     * @param null $sQuestionType
     * @param null $gid
     * @return array|mixed|QuestionCreate|null
     * @throws CException
     */
    private function _getQuestionObject($iQuestionId = null, $sQuestionType = null, $gid = null)
    {
        $iSurveyId = App()->request->getParam('sid') ?? App()->request->getParam('surveyid');
        $oQuestion =  Question::model()->findByPk($iQuestionId);

        if ($oQuestion == null) {
            $oQuestion = QuestionCreate::getInstance($iSurveyId, $sQuestionType);
        }

        if ($sQuestionType != null) {
            $oQuestion->type = $sQuestionType;
        }

        if ($gid != null) {
            $oQuestion->gid = $gid;
        }

        return $oQuestion;
    }

    /**
     * Method to store and filter questionData for a new question
     * @throws CException
     */
    private function _newQuestion($aQuestionData = null, $subquestion = false)
    {
        $iSurveyId = App()->request->getParam('sid') ?? App()->request->getParam('surveyid');
        $oSurvey = Survey::model()->findByPk($iSurveyId);
        $iQuestionGroupId = App()->request->getParam('gid');

        $aQuestionData = array_merge([
                'sid' => $iSurveyId,
                'gid' => App()->request->getParam('gid'),
                'type' => SettingsUser::getUserSettingValue(
                    'preselectquestiontype',
                    null,
                    null,
                    null,
                    App()->getConfig('preselectquestiontype')
                ),
                'other' => 'N',
                'mandatory' => 'N',
                'relevance' => 1,
                'group_name' => '',
                'modulename' => '',
        ], $aQuestionData);
        unset($aQuestionData['qid']);

        if ($subquestion) {
            foreach ($oSurvey->allLanguages as $sLanguage) {
                unset($aQuestionData[$sLanguage]);
            }
        } else {
            \Yii::import('application.helpers.common_helper', true);
            $aQuestionData['question_order'] = getMaxQuestionOrder($iQuestionGroupId);
        }

        $oQuestion = new Question();
        $oQuestion->setAttributes($aQuestionData, false);
        if ($oQuestion == null) {
            throw new CException("Object creation failed, input array malformed or invalid");
        }

        $saved = $oQuestion->save();
        if ($saved == false) {
            //throw new CException(
               // "Object creation failed, couldn't save.\n ERRORS:".print_r(
                 //   $oQuestion->getErrors(),
                  //  true
               // );
            //);
        } else {
            $i10N = [];
            foreach ($oSurvey->allLanguages as $sLanguage) {
                $i10N[$sLanguage] = new QuestionL10n();
                $i10N[$sLanguage]->setAttributes([
                    'qid' => $oQuestion->qid,
                    'language' => $sLanguage,
                    'question' => '',
                    'help' => '',
                ], false);
                $i10N[$sLanguage]->save();
            }

        }

        return $oQuestion;
    }

    /**
     * Method to store and filter questionData for editing a question
     * @param $oQuestion
     * @param $aQuestionData
     * @return
     * @throws CException
     */
    private function _editQuestion(&$oQuestion, $aQuestionData)
    {
        $oQuestion->setAttributes($aQuestionData, false);
        if ($oQuestion == null) {
            throw new CException("Object update failed, input array malformed or invalid");
        }

        $saved = $oQuestion->save();
        if ($saved == false) {
            throw new CException("Object update failed, couldn't save. ERRORS:".print_r($oQuestion->getErrors(), true));
        }
        return $oQuestion;
    }

    /**
     * @todo document me.
     * @param $oQuestion
     * @param $dataSet
     * @return bool
     */
    private function _unparseAndSetGeneralOptions(&$oQuestion, $dataSet)
    {
        $storeValid = true;
        $aQuestionBaseAttributes = $oQuestion->attributes;

        foreach ($dataSet as $sAttributeKey => $aAttributeValueArray) {
            if ($sAttributeKey === 'debug' || !isset($aAttributeValueArray['formElementValue'])) {
                continue;
            }
            if (array_key_exists($sAttributeKey, $aQuestionBaseAttributes)) {
                $oQuestion->$sAttributeKey = $aAttributeValueArray['formElementValue'];
            } else {
                $storeValid = $storeValid &&
                    QuestionAttribute::model()->setQuestionAttribute(
                        $oQuestion->qid,
                        $sAttributeKey,
                        $aAttributeValueArray['formElementValue']
                    );
            }
        }

        $storeValid = $storeValid && $oQuestion->save();

        return $storeValid;
    }

    /**
     * @todo document me
     * @param $oQuestion
     * @param $dataSet
     * @return bool
     */
    private function _unparseAndSetAdvancedOptions(&$oQuestion, $dataSet)
    {
        $storeValid = true;
        $aQuestionBaseAttributes = $oQuestion->attributes;

        foreach ($dataSet as $sAttributeCategory => $aAttributeCategorySettings) {
            if ($sAttributeCategory === 'debug') {
                continue;
            }
            foreach ($aAttributeCategorySettings as $sAttributeKey => $aAttributeValueArray) {
                if (!isset($aAttributeValueArray['formElementValue'])) {
                    continue;
                }
                $newValue = $aAttributeValueArray['formElementValue'];

                if (is_array($newValue)) {
                    foreach ($newValue as $lngKey => $content) {
                        if ($lngKey == 'expression') {
                            continue;
                        }
                        $storeValid = $storeValid &&
                            QuestionAttribute::model()->setQuestionAttributeWithLanguage(
                                $oQuestion->qid,
                                $sAttributeKey,
                                $content,
                                $lngKey
                            );
                    }
                } else {
                    if (array_key_exists($sAttributeKey, $aQuestionBaseAttributes)) {
                        $oQuestion->$sAttributeKey = $newValue;
                    } else {
                        $storeValid = $storeValid &&
                            QuestionAttribute::model()->setQuestionAttribute(
                                $oQuestion->qid,
                                $sAttributeKey,
                                $newValue
                            );
                    }
                }
            }
        }

        $storeValid = $storeValid && $oQuestion->save();

        return $storeValid;
    }

    /**
     * @todo document me.
     * @param $oQuestion
     * @param $dataSet
     * @return bool
     */
    private function _applyI10N(&$oQuestion, $dataSet)
    {
        $storeValid = true;

        foreach ($dataSet as $sLanguage => $aI10NBlock) {
            $i10N = QuestionL10n::model()->findByAttributes(['qid' => $oQuestion->qid,'language' => $sLanguage]);
            $i10N->setAttributes([
                'question' => $aI10NBlock['question'],
                'help' => $aI10NBlock['help'],
                'script' => $aI10NBlock['script'],
            ], false);
            $storeValid = $storeValid && $i10N->save();
        }

        return $storeValid;
    }

    /**
     * @todo document me
     * @param $oQuestion
     * @param $dataSet
     * @return bool
     */
    private function _applyI10NSubquestion($oQuestion, $dataSet)
    {
        $storeValid = true;

        foreach ($oQuestion->survey->allLanguages as $sLanguage) {
            $aI10NBlock = $dataSet[$sLanguage];
            $i10N = QuestionL10n::model()->findByAttributes(['qid' => $oQuestion->qid,'language' => $sLanguage]);
            $i10N->setAttributes([
                'question' => $aI10NBlock['question'],
                'help' => $aI10NBlock['help'],
            ], false);
            $storeValid = $storeValid && $i10N->save();
        }

        return $storeValid;
    }

    /**
     * @todo document me
     * @param $oAnswer
     * @param $oQuestion
     * @param $dataSet
     * @return bool
     */
    private function _applyAnswerI10N($oAnswer, $oQuestion, $dataSet)
    {
        $storeValid = true;

        foreach ($oQuestion->survey->allLanguages as $sLanguage) {
            $i10N = AnswerL10n::model()->findByAttributes(['aid' => $oAnswer->aid,'language' => $sLanguage]);
            if ($i10N == null) {
                $i10N = new AnswerL10n();
                $i10N->setAttributes([
                    'aid' => $oAnswer->aid,
                    'language' => $sLanguage
                ], false);
            }
            $i10N->setAttributes([
                'answer' => $dataSet[$sLanguage]['answer'],
            ], false);
            $storeValid = $storeValid && $i10N->save();
        }

        return $storeValid;
    }

    /**
     * @todo document me.
     * @param $oQuestion
     * @param $dataSet
     */
    private function _cleanSubquestions(&$oQuestion, &$dataSet)
    {
        $aSubquestions = $oQuestion->subquestions;
        array_walk(
            $aSubquestions,
            function ($oSubquestion) use (&$dataSet) {
                $exists = false;
                foreach ($dataSet as $scaleId => $aSubquestions) {
                    foreach ($aSubquestions as $i => $aSubquestionDataSet) {
                        if (((is_numeric($aSubquestionDataSet['qid'])
                                    && $oSubquestion->qid == $aSubquestionDataSet['qid'])
                            ||  $oSubquestion->title == $aSubquestionDataSet['title'])
                            && ($oSubquestion->scale_id == $scaleId)
                        ) {
                            $exists = true;
                            $dataSet[$scaleId][$i]['qid'] = $oSubquestion->qid;
                        }

                        if (!$exists) {
                            $oSubquestion->delete();
                        }
                    }
                }
            }
        );
    }

    /**
     * @todo document me
     * @param $oQuestion
     * @param $dataSet
     * @return bool
     * @throws CException
     */
    private function _storeSubquestions(&$oQuestion, $dataSet)
    {
        $storeValid = true;
        $this->_cleanSubquestions($oQuestion, $dataSet);
        foreach ($dataSet as $scaleId => $aSubquestions) {
            foreach ($aSubquestions as $aSubquestionDataSet) {
                $oSubQuestion = Question::model()->findByPk($aSubquestionDataSet['qid']);
                if ($oSubQuestion != null) {
                    $oSubQuestion = $this->_editQuestion($oSubQuestion, $aSubquestionDataSet);
                } else {
                    $aSubquestionDataSet['parent_qid'] = $oQuestion->qid;
                    $oSubQuestion = $this->_newQuestion($aSubquestionDataSet, true);
                }
                $storeValid = $storeValid && $this->_applyI10NSubquestion($oSubQuestion, $aSubquestionDataSet);
            }
        }

        return $storeValid;
    }

    /**
     * @todo document me.
     *
     * @param $oQuestion
     * @param $dataSet
     */
    private function _cleanAnsweroptions(&$oQuestion, &$dataSet)
    {
        $aAnsweroptions = $oQuestion->answers;
        array_walk(
            $aAnsweroptions,
            function ($oAnsweroption) use (&$dataSet) {
                $exists = false;
                foreach ($dataSet as $scaleId => $aAnsweroptions) {
                    foreach ($aAnsweroptions as $i => $aAnsweroptionDataSet) {
                        if (((is_numeric($aAnsweroptionDataSet['aid'])
                                    && $oAnsweroption->aid == $aAnsweroptionDataSet['aid'])
                            ||  $oAnsweroption->code == $aAnsweroptionDataSet['code'])
                            && ($oAnsweroption->scale_id == $scaleId)
                        ) {
                            $exists = true;
                            $dataSet[$scaleId][$i]['aid'] = $oAnsweroption->aid;
                        }

                        if (!$exists) {
                            $oAnsweroption->delete();
                        }
                    }
                }
            }
        );
    }

    /**
     * @todo document me
     * @param $oQuestion
     * @param $dataSet
     * @return bool
     * @throws CException
     */
    private function _storeAnswerOptions(&$oQuestion, $dataSet)
    {
        $storeValid = true;
        $this->_cleanAnsweroptions($oQuestion, $dataSet);
        foreach ($dataSet as $scaleId => $aAnswerOptions) {
            foreach ($aAnswerOptions as $aAnswerOptionDataSet) {
                $aAnswerOptionDataSet['sortorder'] = (int) $aAnswerOptionDataSet['sortorder'];
                $oAnswer = Answer::model()->findByPk($aAnswerOptionDataSet['aid']);
                if ($oAnswer == null) {
                    $oAnswer = new Answer();
                    $oAnswer->qid = $oQuestion->qid;
                    unset($aAnswerOptionDataSet['aid']);
                    unset($aAnswerOptionDataSet['qid']);
                }
                $oAnswer->setAttributes($aAnswerOptionDataSet, false);
                $answerSaved = $oAnswer->save();
                if (!$answerSaved) {
                    throw new CException(
                        "Answer option couldn't be saved. Error: ".print_r(
                            $oAnswer->getErrors(),
                            true
                        )
                    );
                }
                $storeValid = $storeValid && $this->_applyAnswerI10N($oAnswer, $oQuestion, $aAnswerOptionDataSet);
            }
        }
        return $storeValid;
    }

    /**
     * @param $oQuestion Question
     * @return array
     * @throws CException
     * @todo document me.
     */
    private function _getCompiledQuestionData(&$oQuestion)
    {
        \Yii::import('application.helpers.expressions.em_manager_helper', true);
        LimeExpressionManager::StartProcessingPage(false, true);
        $aQuestionDefinition = array_merge($oQuestion->attributes, ['typeInformation' => $oQuestion->questionType]);
        $oQuestionGroup = QuestionGroup::model()->findByPk($oQuestion->gid);
        $aQuestionGroupDefinition = array_merge($oQuestionGroup->attributes, $oQuestionGroup->questionGroupL10ns);

        $aScaledSubquestions = $oQuestion->getOrderedSubQuestions();
        foreach ($aScaledSubquestions as $scaleId => $aSubquestions) {
            $aScaledSubquestions[$scaleId] = array_map(function ($oSubQuestion) {
                return array_merge($oSubQuestion->attributes, $oSubQuestion->questionL10ns);
            }, $aSubquestions);
        }

        $aScaledAnswerOptions = $oQuestion->getOrderedAnswers();
        foreach ($aScaledAnswerOptions as $scaleId => $aAnswerOptions) {
            $aScaledAnswerOptions[$scaleId] = array_map(function ($oAnswerOption) {
                return array_merge($oAnswerOption->attributes, $oAnswerOption->answerL10ns);
            }, $aAnswerOptions);
        }

        $aReplacementData = [];
        $questioni10N = [];
        foreach ($oQuestion->questionL10ns as $lng => $oQuestionI10N) {
            $questioni10N[$lng] = $oQuestionI10N->attributes;
            templatereplace(
                $oQuestionI10N->question,
                array(),
                $aReplacementData,
                'Unspecified',
                false,
                $oQuestion->qid
            );
            $questioni10N[$lng]['question_expression'] = viewHelper::stripTagsEM(
                LimeExpressionManager::GetLastPrettyPrintExpression()
            );

            templatereplace($oQuestionI10N->help, array(), $aReplacementData, 'Unspecified', false, $oQuestion->qid);
            $questioni10N[$lng]['help_expression'] = viewHelper::stripTagsEM(
                LimeExpressionManager::GetLastPrettyPrintExpression()
            );
        }

        LimeExpressionManager::FinishProcessingPage();

        return [
            'question' => $aQuestionDefinition,
            'questiongroup' => $aQuestionGroupDefinition,
            'i10n' => $questioni10N,
            'subquestions' => $aScaledSubquestions,
            'answerOptions' => $aScaledAnswerOptions,
        ];
    }

    /**
     * Renders template(s) wrapped in header and footer
     *
     * @param string $sAction Current action, the folder to fetch views from
     * @param string|array $aViewUrls View url(s)
     * @param array $aData Data to be passed on. Optional.
     * @param bool $sRenderFile
     * @throws CHttpException
     */
    protected function _renderWrappedTemplate($sAction = 'survey/Question2', $aViewUrls = array(), $aData = array(), $sRenderFile = false)
    {
        parent::_renderWrappedTemplate($sAction, $aViewUrls, $aData, $sRenderFile);
    }
}
