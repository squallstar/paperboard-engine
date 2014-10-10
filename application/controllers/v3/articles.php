<?php
/**
 * Articles controller
 *
 * @package     Cronycle
 * @author      Nicholas Valbusa - info@squallstar.it - @squallstar
 * @copyright   Copyright (c) 2014, Squallstar
 * @license     GNU/GPL (General Public License)
 * @link        http://squallstar.it
 *
 */

class Articles_Controller extends Cronycle_Controller
{
  public function __construct()
  {
    parent::__construct();
    $this->load->model('model_collections', 'collections');
  }

  public function view($article_id)
  {
    $article = collection('articles')->findOne(
      ['id' => $article_id],
      ['_id' => false, 'has_image' => false, 'images_processed' => false, 'extractor' => false, 'source' => false, 'fetched' => false, 'fetched_at' => false, 'processed_at' => false]
    );

    if ($article)
    {
      $this->json(200, $article);
    }
    else
    {
      $this->json(404, ['errors' => ['Article not found']]);
    }
  }

  public function suggested($article_id)
  {
    $article = collection('articles')->findOne(['id' => $article_id], ['_id' => false, 'entities' => true]);

    if (!isset($article['entities']) || count($article['entities']) == 0)
    {
      return $this->json(200, []);
    }

    $entities = [];

    foreach ($article['entities'] as $entity)
    {
      $entities[] = $entity['ltext'];
      if (count($entities) >= 2) break;
    }

    $result = collection('articles')->find(
        [
        'entities.ltext' => [
          '$in' => $entities
        ]
      ],
      $this->collections->articles_excluded_fields()
    )->sort(['published_at' => -1])->hint(['entities.ltext' => 1])->limit(10);

    $articles = [];

    foreach (iterator_to_array($result) as $article)
    {
      if (isset($articles[$article['name']]) || $article['id'] == $article_id)
      {
        continue;
      }

      $articles[$article['name']] = $article;

      if (count($articles) == 5) break;
    }

    return $this->json(200, array_values($articles));
  }
}