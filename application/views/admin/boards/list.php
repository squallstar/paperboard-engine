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

      <tr data-id="<?php echo $board['id']; ?>">
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
          <?php if (isset($board['tags'])) { ?>
            <p>
              <input style="padding: 5px; width: 230px" type="text" placeholder="Tags" name="tags" value="<?php echo implode(', ', $board['tags']); ?>" /><br />
              <small>Featured</small> <input type="checkbox" name="featured" <?php echo $board['featured'] ? 'checked="checked"' : ''; ?> /><br />
              <small><a href="#" class="save-data">Save</a></small>
            </p>
          <?php } ?>
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

<script>
$(document).ready(function() {

  $('table').on('click', '.save-data', function(event) {
    event.preventDefault();

    var $el = $(this),
        $tr = $el.closest('tr'),
        id = $tr.data('id'),
        tags = $tr.find("input[name='tags']").val(),
        featured = $tr.find("input[name='featured']").prop('checked');

    $el.html('<strong>Saving...</strong>');

    $.post("boards/update/" + id, {
      tags: tags,
      featured: featured
    }, function(data) {
      $el.text(data == 1 ? 'Saved!' : 'Could not save');
    });
  });
});
</script>