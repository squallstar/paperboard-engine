<!DOCTYPE html>
<html>
<head>
  <title>Admin</title>
  <script src="http://code.jquery.com/jquery-2.1.1.min.js"></script>
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