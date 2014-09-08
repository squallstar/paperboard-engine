<?php

get_instance()->load->helper('url');

function load_admin_view($view, $data)
{
  return get_instance()->load->view('admin/layout', [
    '_view' => $view,
    'data' => $data
  ]);
}