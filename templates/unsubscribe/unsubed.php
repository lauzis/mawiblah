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
            <h1><?php _e('You have been unsubscribed!'); ?></h1>
            <p><?php _e('We hate to see you go. But thanks for beeing with us. And good luck!.'); ?></p>
        </div>

    </section>
</main>
</body>
</html>
