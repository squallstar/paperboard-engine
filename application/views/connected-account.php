<!DOCTYPE html>
<html>
<head>
</head>
<body>
  <script type="text/javascript">
    if (window.opener) {
      window.opener.Paperboard.vent.trigger("connected:account", "<?php echo $type; ?>");
    }
    window.close();
  </script>
</body>
</html>