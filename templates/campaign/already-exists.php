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
            <h1><?php _e('Could not create campaign with such title!'); ?></h1>
            <p><?php _e('Could not create campaign with such title! Campaign with such title already exists!'); ?></p>
        </div>

    </section>
</main>
</body>
</html>
