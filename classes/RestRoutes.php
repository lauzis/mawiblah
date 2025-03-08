<?php

namespace Mawiblah;

class RestRoutes
{

    public static function getHtmlTemplate(\WP_REST_Request $request)
    {
        $post = $request->get_json_params();

        $template = $post['template'];
        $templateContent = Templates::getEmailTemplateByName($template);
        $templateContent = do_shortcode($templateContent);

        return [
            'status' => 'ok',
            'template' => $templateContent,
            'templateName' => $template
        ];
    }

    public static function test(\WP_REST_Request $request)
    {
        return [
            'status' => 'ok'
        ];
    }
}
