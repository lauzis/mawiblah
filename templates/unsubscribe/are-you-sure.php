<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple HTML Template</title>
    <style>
        .message{
            padding: 60px;
        }
        .message div {
            text-align: left;
            max-width: 600px;
            margin: auto;

        }

        h1{
            font-sioze:24px;
        }
        h2{
            font-size:20px;
        }
        p {
            padding:0;
            margin:0;
            font-size:16px;
            line-height: 1.5;
            padding-bottom:20px;
        }

        textarea{
            width:100%;
            height:250px;
        }

        form {
            display: flex;
            flex-direction: column;
        }

        label {
            padding:10px 0;
            font-weight: bold;
            display: block;
        }
        button {
            padding:10px 20px;
            margin-top:20px;
        }
    </style>
</head>
<body>
<main>
    <section class="message">
        <div>
            <h1><?php _e('Are you sure?', 'mawiblah'); ?></h1>
            <p><?php _e('Are you sure that you want to unsubscribe?', 'mawiblah'); ?></p>

            <h2><?php _e('Help us to get better', 'mawiblah'); ?></h2>
            <p><?php _e('We are sad to see you go, but could you provide us feedback? We want to get better. Your opinion is highly valuable to us.', 'mawiblah'); ?></p>
            <form action="<?= $formUrl; ?>" method="POST">

                <label><?php _e('Let us know what we can improve','mawiblah'); ?></label>
                <textarea id="feedback" name="feedback" ></textarea>

                <input type="hidden" name="email" value="<?= $email; ?>"/>
                <input type="hidden" name="subscriberId" value="<?= $subscriberId; ?>"/>
                <input type="hidden" name="unsubToken" value="<?= $unsubToken; ?>"/>
                <button type="submit" name="submit" value="<?php _e('Unsubscribe','mawiblah'); ?>">
                    <?php _e('Unsubscribe','mawiblah'); ?>
                </button>
            </form>
        </div>
    </section>
</main>
</body>
</html>
