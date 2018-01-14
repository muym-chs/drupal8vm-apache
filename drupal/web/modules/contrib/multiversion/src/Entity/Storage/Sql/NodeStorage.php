<?php

namespace Drupal\multiversion\Entity\Storage\Sql;

use Drupal\multiversion\Entity\Storage\ContentEntityStorageInterface;
use Drupal\multiversion\Entity\Storage\ContentEntityStorageTrait;
use Drupal\node\NodeStorage as CoreNodeStorage;

/**
 * Storage handler for nodes.
 */
class NodeStorage extends CoreNodeStorage implements ContentEntityStorageInterface {

  use ContentEntityStorageTrait {
    delete as deleteEntities;
  }

  /**
   * {@inheritdoc}
   *
   * @todo: {@link https://www.drupal.org/node/2597534 Figure out why we need
   * this}, core seems to solve it some other way.
   */
  public function delete(array $entities) {
    // Delete all menus and comments before deleting the nodes.
    try {
      $entity_type_manager = \Drupal::entityTypeManager();
      /** @var \Drupal\multiversion\Entity\Storage\Sql\CommentStorage $comment_storage */
      $comment_storage = $entity_type_manager->getStorage('comment');
      /** @var \Drupal\menu_link_content\Entity\MenuLinkContent $menu_link_content_storage */
      $menu_link_content_storage = $entity_type_manager->getStorage('menu_link_content');
      /** @var \Drupal\node\Entity\Node $entity */
      foreach ($entities as $entity) {
        // Get the node internal path.
        $node_url = $entity->toUrl()->getInternalPath();
        /** @var \Drupal\menu_link_content\MenuLinkContentInterface[] $node_menu_items */
        $node_menu_items = $menu_link_content_storage->loadByProperties(['link.uri' => 'entity:' . $node_url]);
        // Check and delete menu items.
        if (!empty($node_menu_items)) {
          $menu_link_content_storage->delete($node_menu_items);
        }
        // Check and delete comments.
        if ($entity->comment) {
          $comments = $comment_storage->loadThread($entity, 'comment', 1);
          $comment_storage->delete($comments);
        }
      }
    }
    catch (\Exception $e) {
      // Failing likely due to comment module not being enabled. But we also
      // don't want node delete to fail because of broken comments.
    }
    $this->deleteEntities($entities);
  }

}
