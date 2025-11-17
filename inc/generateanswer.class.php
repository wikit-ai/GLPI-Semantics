<?php
/**
 * -------------------------------------------------------------------------
 * Wikit Semantics plugin for GLPI
 * Copyright (C) 2025 by the Wikit Development Team.
 * -------------------------------------------------------------------------
 */


if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * Class PluginWikitsemanticsGenerateAnswer
 */
class PluginWikitsemanticsGenerateAnswer extends CommonDBTM
{
    public function prepareToGenerateAnswer($ticketId)
    {
        $ticket = new Ticket();

        $ticketitems = $ticket->find(['id' => $ticketId]);
        $config = new PluginWikitsemanticsConfig();

        foreach ($ticketitems as $ticketitem) {
            return $config->testConnection(['query' => htmlspecialchars_decode($ticketitem['content'])]);
        }
        return false;
    }

    public function showAjaxModal()
    {
        echo Ajax::createIframeModalWindow(
            'popupAnswer',
            PLUGIN_WIKITSEMANTICS_WEBDIR . '/front/modalanswer.php',
            [
                'title' => __('Wikit Semantics Application Response', 'wikitsemantics'),
                'reloadonclose' => false,
                'width' => 1180,
                'height' => 500,
            ]
        );
    }

    public function showWikitSemanticsButtonITILFollowup($ticketId)
    {
        $this->showAjaxModal();
        echo Html::scriptBlock(
            "
            document.querySelector('.itilfollowup form[name=asset_form] div.row .order-first .row').innerHTML = '<div class=\"form-field row col-12  mb-2\"><label class=\"col-form-label col-2 text-xxl-end\"> </label><div class=\"col-10  field-container\"><a class=\"btn btn-secondary overflow-hidden text-nowrap\" data-bs-toggle=\"modal\" data-bs-target=\"#popupAnswer\" title=\"" . __(
                'Suggest an answer with AI',
                'wikitsemantics'
            ) . "\" data-bs-toggle=\"tooltip\" data-bs-placement=\"top\" data-bs-original-title=\"" . __(
                'Suggest an answer with AI',
                'wikitsemantics'
            ) . "\" onclick=\"powerActionFollowup()\"><i class=\"fas fa-wand-magic-sparkles\"></i></a></div></div>' + document.querySelector('.itilfollowup form[name=asset_form] div.row .order-first .row').innerHTML;

            function closeFollowup(){
                document.querySelector('#popupAnswer div.modal-body').innerHTML = '<div style=\"display: block; height: 200px;padding: 20px\"><i class=\"fas fa-4x fa-spinner fa-pulse m-5 start-50\" style=\"position: relative;margin: auto !important;\"></i></div>';
            }


            function addAnswerFollowup(result) {
                document.querySelector('#popupAnswer div.modal-body').innerHTML = '<div style=\"display: block; height: 200px;padding: 20px\"><i class=\"fas fa-4x fa-spinner fa-pulse m-5 start-50\" style=\"position: relative;margin: auto !important;\"></i></div>';
                let data_decode = result
                document.querySelector('.itilfollowup form[name=asset_form] div.row div.tox-editor-container iframe').contentWindow.document.body.querySelector('#tinymce p').innerHTML = data_decode.content;
            }

           function powerActionFollowup() {
document.querySelector('#popupAnswer div.modal-body').innerHTML = '<div style=\"display: block; height: 200px;padding: 20px\"><i class=\"fas fa-4x fa-spinner fa-pulse m-5 start-50\" style=\"position: relative;margin: auto !important;\"></i></div>';
                   $.ajax({
                       type: 'POST',
                       url: '" . PLUGIN_WIKITSEMANTICS_WEBDIR . "/ajax/generateanswer.php',
                       data:{
                           'ticketId' : " . $ticketId . ",
                           'answer' : 'addAnswerFollowup',
                           'close' : 'closeFollowup'
                       },
                       success: function(html){
                       document.querySelector('#popupAnswer div.modal-body').innerHTML = html;
                       },
                   });


           }"
        );
    }

    public function showWikitSemanticsButtonITILSolution($ticketId)
    {
        $this->showAjaxModal();
        echo Html::scriptBlock(
            "

            document.querySelector('.itilsolution form[name=asset_form] div.row .order-first .row .form-field .field-container').innerHTML += '<a class=\"btn btn-secondary overflow-hidden text-nowrap\" data-bs-toggle=\"modal\" data-bs-target=\"#popupAnswer\" title=\"" . __(
                'Suggest a solution with AI',
                'wikitsemantics'
            ) . "\" data-bs-toggle=\"tooltip\" data-bs-placement=\"top\" data-bs-original-title=\"" . __(
                'Suggest a solution with AI',
                'wikitsemantics'
            ) . "\" onclick=\"powerActionSolution()\"><i class=\"fas fa-wand-magic-sparkles\"></i></a>';

            function closeSolution(){
                document.querySelector('#popupAnswer div.modal-body').innerHTML = '<div style=\"display: block; height: 200px;padding: 20px\"><i class=\"fas fa-4x fa-spinner fa-pulse m-5 start-50\" style=\"position: relative;margin: auto !important;\"></i></div>';
            }

            function addAnswerSolution(result) {
                document.querySelector('#popupAnswer div.modal-body').innerHTML = '<div style=\"display: block; height: 200px;padding: 20px\"><i class=\"fas fa-4x fa-spinner fa-pulse m-5 start-50\" style=\"position: relative;margin: auto !important;\"></i></div>';
           		let data_decode = result
                document.querySelector('.itilsolution form[name=asset_form] div.row div.tox-editor-container iframe').contentWindow.document.body.querySelector('#tinymce p').innerHTML = data_decode.content;
            }

           function powerActionSolution() {
               document.querySelector('#popupAnswer div.modal-body').innerHTML = '<div style=\"display: block; height: 200px;padding: 20px\"><i class=\"fas fa-4x fa-spinner fa-pulse m-5 start-50\" style=\"position: relative;margin: auto !important;\"></i></div>';
                   $.ajax({
                       type: 'POST',
                       url: '" . PLUGIN_WIKITSEMANTICS_WEBDIR . "/ajax/generateanswer.php',
                       data:{
                           'ticketId' : " . $ticketId . ",
                           'answer' : 'addAnswerSolution',
                           'close' : 'closeSolution'
                       },
                       success: function(html){
                       document.querySelector('#popupAnswer div.modal-body').innerHTML = html;
                       },
                   });


           }"
        );
    }
}
