<h2>Create campaing</h2>

<div class="flex ">
    <div class="flex-column">
        <form action="<?= \Mawiblah\Helpers::getCurrentUrl(); ?>&create-compaign" method="POST" class="create-campaign-form">
            <label for="title">Title</label>
            <input type="text" name="title" id="title" value="<?= isset($campaign) ? $campaign->post_title : ''; ?>">

            <label for="subject">Subject</label>
            <input type="text" name="subject" id="subject" value="<?= isset($campaign) ? $campaign->subject : ''; ?>">

            <label for="contentTitle">Content Title</label>
            <input type="text" name="contentTitle" id="contentTitle" value="<?= isset($campaign) ? $campaign->contentTitle : ''; ?>" />

            <label for="content">Content</label>
            <textarea name="content" id="content"><?= isset($campaign) ? $campaign->post_content : ''; ?></textarea>

            <label for="template">Template</label>
            <select name="template" id="template">
                <?php $templates = \Mawiblah\Templates::getArrayOfEmailTemplates(); ?>
                <?php foreach ($templates as $template): ?>
                    <?php $selected = ''; ?>
                    <?php if (isset($campaign) && $campaign->template === $template) {
                        $selected = 'selected';
                    } ?>
                    <option value="<?= $template ?>" <?= $selected ?>>
                        <?= $template ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="audiences">Audiences</label>
            <?php
                $audiences = \Mawiblah\GravityForms::getArrayOfGravityForms();
            ?>
            <select name="audiences[]" id="audiences" multiple>

                <?php foreach ($audiences as $audience): ?>
                    <?php $selected = ''; ?>
                    <?php if (isset($campaign) && in_array("GF__{$audience['id']}" ,$campaign->audiences)) {
                        $selected = 'selected';
                    } ?>
                    <option <?= $selected ?> value="GF__<?= $audience['id'] ?>">
                        <?= $audience['title'] ?>
                    </option>;
                <?php endforeach; ?>
            </select>

            <input type="submit" value="Create">
        </form>
    </div>
    <div class="flex-column flex-grow">
        <div id="mawiblah-preview">

        </div>
    </div>
</div>
