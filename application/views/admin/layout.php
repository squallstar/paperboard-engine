<!DOCTYPE html>
<html>
<head>
  <title>Admin</title>
  <link rel="stylesheet" type="text/css" href="<?php echo base_url('assets/css/admin.css'); ?>" />
</head>
<body>
  <header>
    <ul>
      <li><a href="/admin/users">Users</a></li>
      <li><a href="/admin/boards">Boards</a></li>
    </ul>
  </header>
  <div id="content"><?php

    if (isset($data['flash']) && $data['flash'])
    {
      echo '<div id="message">&rarr; ' . $data['flash'] . '</div>';
    }

    echo $this->load->view($_view, $data, true);

  ?></div>
  <footer></footer>
</body>
</html>