<form method="post" action="<?php echo current_url(); ?>">
  <input type="text" name="url" placeholder="http://example.org" />
  <input type="submit" value="Extract" />
</form>

<?php if ($article) { ?>
  <hr />
  <pre style="white-space: pre-wrap;"><?php var_dump($article); ?></pre>
<?php } ?>