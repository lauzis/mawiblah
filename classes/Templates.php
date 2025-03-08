<?php

namespace Mawiblah;
class Templates
{

    public static function getEmailTemplateByName($templateName){
        $dir = MAWIBLAH_PLUGIN_DIR . '/email_templates';
        $files = scandir($dir);
        foreach ($files as $file) {
            if ( pathinfo($file, PATHINFO_FILENAME) === $templateName) {
                return file_get_contents($dir . '/' . $file);
            }
        }
        return false;
    }
    public static function getArrayOfEmailTemplates(): array
    {
        $templates = [];
        $dir = MAWIBLAH_PLUGIN_DIR . '/email_templates';
        $files = scandir($dir);

        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..' && !is_dir($dir . '/' . $file)) {
                $templates[] = pathinfo($file, PATHINFO_FILENAME);
            }
        }

        return $templates;
    }

    public static function validateEmailTemplate(string $emailTemplate):bool
    {
        $dir = MAWIBLAH_PLUGIN_DIR . '/email_templates';
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file === $emailTemplate) {
                return true;
            }
        }
        return false;
    }

    public static function copyTemplate( int $campaignId, bool $testMode): string | bool
    {
        $templateName = get_post_meta($campaignId, 'template', true);
        $templateArchived = get_post_meta($campaignId, 'email_template_copied', true);

        $dir = MAWIBLAH_PLUGIN_DIR . '/email_templates/archieved';
        $template = do_shortcode(self::getEmailTemplateByName($templateName));

        if ($template) {
            if (!$templateArchived || $testMode) {
                $filename = $campaignId . '_' . $templateName . '.html';
                file_put_contents($dir . '/' . $filename, $template);
                update_post_meta($campaignId, 'email_template_copied', true);
            }

            return $template;
        }

        return false;
    }
}
