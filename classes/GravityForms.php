<?php

namespace Mawiblah;

class GravityForms
{
    public static function getArrayOfGravityForms(): array
    {
        $GravityForms = \GFAPI::get_forms();
        return $GravityForms;
    }

    public static function getGravityFormWitheEmailFieldIds()
    {
        $forms = self::getArrayOfGravityForms();
        $ids = [];
        foreach ($forms as $form) {
            foreach ($form['fields'] as $field) {
                if ($field->type == 'email') {
                    $id = $field->id;

                    $ids[] = [
                        'formId' => $form['id'],
                        'emailId' => $id
                    ];
                }
            }

        }
        return $ids;
    }


    public static function findEmail($email)
    {

        $formsWihtEmailField = self::getGravityFormWitheEmailFieldIds();
        $emails = [];

        foreach ($formsWihtEmailField as $form) {
            $formId = $form['formId'];
            $emailFieldId = $form['emailId'];


            $searchCriteria = [
                'field_filters' => [
                    [
                        'key' => $emailFieldId,
                        'value' => $email
                    ]
                ]
            ];

            $entries = \GFAPI::get_entries($formId, search_criteria: $searchCriteria);
            foreach ($entries as $entry) {
                $email = $entry[$emailFieldId];
                $emails[$email] = $email;
            }
        }

        return $emails;
    }

    public static function getAllEmails(): array
    {
        $formsWihtEmailField = self::getGravityFormWitheEmailFieldIds();
        $emails = [];

        foreach ($formsWihtEmailField as $form) {
            $formId = $form['formId'];
            $emailFieldId = $form['emailId'];

            $paging = array('offset' => 0, 'page_size' => PHP_INT_MAX);
            $entries = \GFAPI::get_entries($formId, paging: $paging);

            foreach ($entries as $entry) {
                $email = $entry[$emailFieldId];
                $emails[$email] = $email;
            }
        }

        return $emails;
    }

    public static function getAllEmailsForForm($formId): array{
        $form = \GFAPI::get_form($formId);
        $emailFieldId = null;
        foreach($form['fields'] as $field){
            if($field->type == 'email'){
                $emailFieldId = $field->id;
                break;
            }
        }
        if(!$emailFieldId){
            return [];
        }
        $paging = array('offset' => 0, 'page_size' => PHP_INT_MAX);
        $entries = \GFAPI::get_entries($formId, paging: $paging);
        $emails = [];
        foreach ($entries as $entry) {
            $email = $entry[$emailFieldId];
            $emails[$email] = $email;
        }
        return $emails;
    }


    public static function getFormName($formId){
        $form = \GFAPI::get_form($formId);
        return $form['title'];
    }


    public static function updateLastInteraction(){

    }
}
