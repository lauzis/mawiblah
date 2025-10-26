<div class="wrap mawiblah">

    <h1>Mawiblah</h1>

    <section>
        <h2>Campaign stats</h2>


    </section>

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


