<table cellpadding="0" cellspacing="0">

  <thead>
    <th>ID</th>
    <th>User Pic</th>
    <th>Cover</th>
    <th>Name</th>
    <th>Owner</th>
    <th>Stats</th>
  </thead>

  <tbody>
    <?php

    foreach ($boards as $board)
    {
      ?>

      <tr>
        <td>
          #<?php echo $board['id']; ?>
        </td>
        <td style="text-align:center">
          <img src="<?php echo $board['user']['image_url']; ?>" width="50" height="50" style="border-radius:50px" />
        </td>
        <td style="text-align:center">
          <img src="<?php echo $board['cover_asset']['url_archived_small']; ?>" width="150" />
        </td>
        <td>
          <h3><?php echo $board['name']; ?></h3>
          <p><?php echo $board['description']; ?></p>
        </td>
        <td>
          <a href="/admin/users#user<?php echo $board['user']['id']; ?>"><?php echo $board['user']['full_name']; ?></a>
        </td>
        <td>
          <ul>
            <li><?php echo $board['total_source_count']; ?> sources</li>
            <li><?php echo $board['total_links_count']; ?> articles</li>
          </ul>
        </td>
      </tr>

      <?php
    }
    ?>
  </tbody>
</table>