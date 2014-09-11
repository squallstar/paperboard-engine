<table cellpadding="0" cellspacing="0">

  <thead>
    <th>ID</th>
    <th>Pic</th>
    <th>Full name</th>
    <th>Email</th>
    <th>Joined at</th>
    <th>Stats</th>
  </thead>

  <tbody>
    <?php

    foreach ($users as $user)
    {
      ?>

      <tr id="user<?php echo $user['_id']; ?>">
        <td>
          #<?php echo $user['_id']; ?>
        </td>
        <td style="text-align:center">
          <img src="<?php echo $user['avatar']['medium']; ?>" width="50" height="50" style="border-radius:50px" />
        </td>
        <td>
          <h3><?php echo $user['full_name']; ?></h3>
        </td>
        <td>
          <?php echo $user['email']; ?>
        </td>
        <td><?php echo date('Y-m-d H:i', $user['created_at']); ?></td>
        <td>
          <ul>
            <li>
              <a href="/admin/boards?user=<?php echo $user['_id']; ?>"><?php echo $user['total_collections_count']; ?> boards</a>
            </li>

            <?php
              foreach ($user['connected_accounts'] as $account)
              {
                ?>
                <li>
                  <p><?php echo $account['type']; ?>: <strong><?php echo isset($account['screen_name']) ? $account['screen_name'] : $account['access_token']['screen_name']; ?></strong><br />
                    Following <strong><?php echo $account['following']['count']; ?></strong> people. Updated at <?php echo date('Y-m-d H:i', $account['following']['updated_at']); ?><br />
                    <a href="<?php echo current_url() . '?unlink=' . $user['_id'] . '&account=' . $account['id']; ?>">Unlink account</a></p>
                </li>
                <?php
              }
            ?>
            <li>
              <a href="<?php echo $client_url . '?auth_token=' . $user['auth_token']; ?>" target="_blank">Impersonate user</a>
            </li>
            <li>
              <a href="<?php echo current_url() . '?delete=' . $user['_id']; ?>" onclick="return confirm('Are you sure?');">Delete user</a>
            </li>
          </ul>
        </td>
      </tr>

      <?php
    }
    ?>
  </tbody>
</table>