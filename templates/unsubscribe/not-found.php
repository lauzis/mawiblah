<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple HTML Template</title>
    <style>
        .message div {
            text-align: center;
            max-width: 800px;
            margin: auto;
            padding: 60px;
        }
    </style>
</head>
<body>
<main>
    <section class="message">

        <div>
            <h1><?php _e('Could not unsubscribe you!'); ?></h1>
            <p><?php _e('We could not find you in subscribers list.'); ?></p>
            <?php if (is_admin()): ?>
                <pre>
                    <?php print_r($debug); ?>
                </pre>
            <?php endif; ?>
        </div>

    </section>
</main>
</body>
</html>
