<?php

namespace Mawiblah;

class GravityForms
{
    /** Hooks into gform_after_submission to update the last-interaction timestamp for known subscribers. */
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

    /**
     * Returns all Gravity Forms as a raw array from the GFAPI.
     *
     * @return array Array of Gravity Forms form objects.
     */
    public static function getArrayOfGravityForms(): array
    {
        $GravityForms = \GFAPI::get_forms();
        return $GravityForms;
    }

    /**
     * Returns an array of form-ID / email-field-ID pairs for all forms that contain an email field.
     *
     * @return array Array of ['formId' => int, 'emailId' => int] maps.
     */
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

    /**
     * Searches all Gravity Forms entries for a specific email address.
     *
     * @param string $email Email address to search for.
     * @return array Map of found email addresses (deduplicated).
     */
    public static function findEmail(string $email):array
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

    /**
     * Returns all unique email addresses from all Gravity Forms entries.
     *
     * @return array Deduplicated map of email → email strings.
     */
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

    /**
     * Returns all email addresses and their submission dates for a specific Gravity Form.
     *
     * @param int $formId Gravity Forms form ID.
     * @return array Map of email → ['email' => string, 'dateCreated' => string].
     */
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


    /**
     * Returns the title of a Gravity Form.
     *
     * @param int $formId Gravity Forms form ID.
     * @return string Form title.
     */
    public static function getFormName(int $formId):string
    {
        $form = \GFAPI::get_form($formId);
        return $form['title'];
    }

    /** Returns true if both the GFForms and GFAPI classes are available (Gravity Forms is active). */
    public static function isGravityPluginActive(): bool
    {
        return class_exists('GFForms') && class_exists('GFAPI');;
    }

    /**
     * Synchronises Gravity Forms entries with Mawiblah subscriber audiences.
     *
     * For each form, creates or reuses a matching audience taxonomy term and imports
     * all entries as subscribers. Skips forms whose last entry predates the last sync
     * date unless $force is true.
     *
     * @param bool $force When true, re-syncs all forms even if already up to date.
     * @return array Sync stats: checked, skipped, forms_processed, subscribers_created, subscribers_updated, total_entries_processed.
     */
    public static function syncWithAudiencePostType(bool $force = false):array
    {
        $forms = self::getArrayOfGravityForms();
        $syncStats = [
            'checked' => 0,
            'skipped' => 0,
            'forms_processed' => 0,
            'subscribers_created' => 0,
            'subscribers_updated' => 0,
            'total_entries_processed' => 0
        ];

        foreach ($forms as $form) {
            $syncStats['checked']++;

            $formId = $form['id'];
            $audienceName = GravityForms::getFormName($formId) . " (Gravity Forms)";
            $lastModification = self::getDateOfLastEntry($formId);
            $mawiblahAudience = Subscribers::getGFAudience($formId, $audienceName);

            if ($mawiblahAudience) {
                $lastSyncDate = Subscribers::getLastSyncDate($mawiblahAudience->id);

                if ($lastSyncDate < $lastModification || $force) {

                    $emails = self::getAllEmailsForForm($formId);
                    foreach ($emails as $email => $info) {
                        $dateCreated = strtotime($info['dateCreated']);

                        $subscriber = Subscribers::getSubscriber($email);
                        if ($subscriber) {
                            $syncStats['subscribers_updated']++;
                            Subscribers::updateLastInteraction($subscriber->id);
                        } else {
                            $syncStats['subscribers_created']++;
                            $subscriber = Subscribers::addSubscriber($email);
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
                    $syncStats['forms_processed']++;
                    $syncStats['total_entries_processed'] += count($emails);
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
    public static function getDateOfLastEntry(int $formId): int|null
    {
        $paging = array('offset' => 0, 'page_size' => 1);
        $entries = \GFAPI::get_entries($formId, paging: $paging);

        if (empty($entries)) {

                return null;
        }

        $entryDate = strtotime($entries[0]['date_created']);

        return $entryDate;
    }
}
