<?php

namespace Mawiblah;

class GravityForms
{
    public static function init()
    {
        add_action('gform_after_submission', function ($entry, $form) {
            foreach ($form['fields'] as $field) {
                if ($field->type === 'email') {
                    $emailFieldId = $field->id;
                    if (!empty($entry[$emailFieldId])) {
                        $email = $entry[$emailFieldId];
                        // Perform your logic with the email
                        $subscriber = Subscribers::getSubscriber($email);
                        if ($subscriber) {
                            Subscribers::updateLastInteraction($subscriber->id);
                        }
                    }
                }
            }
        }, 10, 2);
    }

    public static function getArrayOfGravityForms(): array
    {
        $GravityForms = \GFAPI::get_forms();
        return $GravityForms;
    }

    public static function getGravityFormWithEmailFieldIds()
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

        $formsWihtEmailField = self::getGravityFormWithEmailFieldIds();
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
        $formsWihtEmailField = self::getGravityFormWithEmailFieldIds();
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

    public static function getAllEmailsForForm(int $formId): array
    {
        $form = \GFAPI::get_form($formId);
        $emailFieldId = null;
        foreach ($form['fields'] as $field) {
            if ($field->type == 'email') {
                $emailFieldId = $field->id;
                break;
            }
        }
        if (!$emailFieldId) {
            return [];
        }
        $paging = array('offset' => 0, 'page_size' => PHP_INT_MAX);
        $entries = \GFAPI::get_entries($formId, paging: $paging);
        $emails = [];
        foreach ($entries as $entry) {
            $dateCreated = $entry['date_created'];
            $email = $entry[$emailFieldId];
            $emails[$email] = [
                'email' => $email,
                'dateCreated' => $dateCreated
            ];
        }
        return $emails;
    }


    public static function getFormName($formId)
    {
        $form = \GFAPI::get_form($formId);
        return $form['title'];
    }

    public static function isGravityPluginActive(): bool
    {
        return class_exists('GFForms');
    }

    public static function syncWithAudiencePostType()
    {
        $forms = self::getArrayOfGravityForms();
        $syncStats = [
            'checked' => 0,
            'skipped' => 0
        ];

        foreach ($forms as $form) {
            $syncStats['checked']++;

            $formId = $form['id'];
            $audienceName = GravityForms::getFormName($formId) . " (Gravity Forms)";
            $lastModification = self::getDateOfLastEntry($formId);
            $mawiblahAudience = Subscribers::getGFAudience($formId, $audienceName);

            if ($mawiblahAudience) {
                $lastSyncDate = Subscribers::getLastSyncDate($mawiblahAudience->id);

                if ($lastSyncDate < $lastModification) {
                    $emails = self::getAllEmailsForForm($formId);
                    foreach ($emails as $email => $info) {
                        $dateCreated = $info['dateCreated'];
                        $subscriber = Subscribers::getSubscriber($email);
                        if ($subscriber) {
                            Subscribers::updateLastInteraction($subscriber->id);
                        } else {
                            $subscriber = Subscribers::addSubscriber($email, $formId);
                        }
                        Subscribers::addSubscriberToAudience($subscriber->id, $mawiblahAudience->id);

                        if (!$subscriber->firstInteraction || $dateCreated < $subscriber->firstInteraction) {
                            Subscribers::updateFirstInteraction($subscriber->id, $dateCreated);
                        }

                        if (!$subscriber->lastInteraction || $dateCreated > $subscriber->lastInteraction) {
                            Subscribers::updateLastInteraction($subscriber->id, $dateCreated);
                        }
                    }
                    Subscribers::updateLastSyncDate($mawiblahAudience->id, $lastModification);
                } else {
                    $syncStats['skipped']++;
                }
            }
        }

        return $syncStats;
    }

    /**
     * Gets the date of the last entry for a specific form
     *
     * @param int $formId The ID of the Gravity Form
     * @return string|null The creation date of the last entry or null if no entries exist
     */
    public static function getDateOfLastEntry(int $formId): string|null
    {
        $paging = array('offset' => 0, 'page_size' => 1);
        $entries = \GFAPI::get_entries($formId, paging: $paging);

        if (empty($entries)) {

                return null;
        }

        $entryDate = $entries[0]['date_created'];

        return $entryDate;
    }
}
