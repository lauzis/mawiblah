<div class="wrap mawiblah">

    <h1>FU M Mail</h1>
    <p>
        Fine. I will do it myself. I will make my own mailchinp with blackjack and hookers.
    </p>







    <h2>Gravity forms (Audiences)</h2>
    <table>
        <thead>
        <tr>
            <th>Id</th>
            <th>Name</th>
            <th>Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php
        $forms = \Mawiblah\GravityForms::getArrayOfGravityForms();
        foreach ($forms as $form) {
            echo "<tr>";
            echo "<td>" . $form['id'] . "</td>";
            echo "<td>" . $form['title'] . "</td>";
            echo "<td><a href=''>Edit</a> | <a href=''>Delete</a></td>";
            echo "</tr>";
        }
        ?>
        </tbody>
    </table>



    <h2>List of email templates</h2>
    <table>
        <thead>
        <tr>
            <th>Id</th>
            <th>Name</th>
            <th>Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php
        $templates = \Mawiblah\Templates::getArrayOfEmailTemplates();
        foreach ($templates as $template) {
            echo "<tr>";
            echo "<td>" . $template . "</td>";
            echo "<td>" . $template . "</td>";
            echo "<td><a href=''>Edit</a> | <a href=''>Delete</a></td>";
            echo "</tr>";
        }
        ?>
        </tbody>
    </table>


    <h2>Compaigns</h2>
    <table>
        <thead>
        <tr>
            <th>Id</th>
            <th>Name</th>
            <th>Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php
        $campaigns = \Mawiblah\Helpers::getArrayOfCampaigns();
        foreach ($campaigns as $campaign) {
            echo "<tr>";
            echo "<td>" . $campaign['ID'] . "</td>";
            echo "<td>" . $campaign['title'] . "</td>";
            echo "<td><a href=''>Edit</a> | <a href=''>Delete</a></td>";
            echo "</tr>";
        }
        ?>
        </tbody>
    </table>
</div>


