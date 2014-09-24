<form method="post" action="<?php echo current_url(); ?>">
  <table cellpadding="0" cellspacing="0">

    <thead>
      <th>Host</th>
      <th>XPath</th>
      <th>Example url</th>
      <th>Added at</th>
      <th>Stats</th>
    </thead>

    <tbody>

      <tr>
        <td align="center"><input type="text" name="host" placeholder="Host"/></td>
        <td align="center"><input style="font-family:monospace" type="text" name="xpath" placeholder="XPath:"/></td>
        <td align="center"><input type="text" name="example" placeholder="Example url"/></td>
        <td>
          <input type="checkbox" name="cleanup" value="Y" /> Cleanup
        </td>
        <td align="center"><input type="submit" value="Add rule"/></td>
      </tr>

      <?php

      foreach ($rules as $rule)
      {
        ?>

        <tr id="<?php echo $rule['_id']->{'$id'}; ?>">
          <td>
            <h3 class="host"><?php echo $rule['host']; ?></h3>
          </td>
          <td>
            <p style="font-family:monospace" class="xpath"><?php echo $rule['xpath']; ?></p>
            <?php if ($rule['cleanup']) echo '<p><small>(with cleanup)</small></p>'; ?>
          </td>
          <td style="font-size:12px">
            <a href="<?php echo $rule['example']; ?>" target="_blank" class="example-url"><?php echo $rule['example']; ?></a>
            <?php if (isset($rule['example_fetch'])) { ?>
              <article>
                <p><strong><?php echo $rule['example_fetch']['name']; ?></strong></p>
                <p><small><?php echo $rule['example_fetch']['description']; ?></small></p>
                <?php if (isset($rule['example_fetch']['lead_image'])) { ?><p><img src="<?php echo $rule['example_fetch']['lead_image']['url_original']; ?>" width="100"/></p><?php } ?>
              </article>
            <?php } ?>
          </td>
          <td style="white-space: nowrap;"><?php echo date('Y-m-d H:i', $rule['added_at']); ?></td>
          <td style="font-size:12px">
            <ul>
              <li>
                <?php echo $rule['checked_at'] ? 'Checked at:&nbsp;' . date('d/m/Y-H:i', $rule['checked_at']) : '<strong style="color:red;">Not checked</strong>'; ?>
              </li>
              <li>
                <a href="<?php echo current_url() . '?check=' . $rule['_id']->{'$id'}; ?>#<?php echo $rule['_id']->{'$id'}; ?>">Check</a>
              </li>
              <li>
                <a href="#" class="edit-rule">Edit</a>
              </li>
              <li>
                <a href="<?php echo current_url() . '?delete=' . $rule['_id']; ?>" onclick="return confirm('Are you sure?');">Delete rule</a>
              </li>
            </ul>
          </td>
        </tr>

        <?php
      }
      ?>
    </tbody>
  </table>
</form>

<script>
$(document).ready(function() {

  $('table').on('click', '.edit-rule', function(event) {
    event.preventDefault();

    var $el = $(this),
        $tr = $el.closest('tr'),
        id = $tr.attr('id'),
        host = prompt("Host", $tr.find('.host').html()),
        xpath = prompt("XPath", $tr.find('.xpath').html()),
        example = prompt("Example Article URL", $tr.find('.example-url').html()),
        cleanup = confirm("Cleanup before applying xpath rules?\n\n[Cancel = No] [OK = Yes]")
    ;

    if (confirm("Please confirm that you're about to update the rule.") == false) return;

    $tr.css('opacity', '0.5');

    $.post("parsers/update/" + id, {
      host: host,
      xpath: xpath,
      example: example,
      cleanup: cleanup
    }, function(data) {
      if (data == 1) {
        $tr.css('opacity', 1);
        $tr.find('.host').html(host);
        $tr.find('.xpath').html(xpath);
        $tr.find('.example-url').html(example);
      } else {
        alert("Cannot save " + host);
      }
    });
  });
});
</script>

<style>
a {word-break: break-all;text-decoration: none;color:#147FDF;}
</style>