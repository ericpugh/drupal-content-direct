<?php

namespace Drupa\content_direct;

/**
 * Class ContentDirectLogStorage.
 *
 * Log module actions to the content_direct_history_log table.
 */
class ContentDirectLogStorage {

  /**
   * Save an entry in the database.
   *
   * @param array $entry
   *   An array containing all the fields of the database record.
   *
   * @return int
   *   The number of updated rows.
   *
   * @throws \Exception
   *   When the database insert fails.
   *
   * @see db_insert()
   */
  public static function insert($entry) {
    $return_value = NULL;
    try {
      $return_value = db_insert('content_direct')
        ->fields($entry)
        ->execute();
    }
    catch (\Exception $e) {
      drupal_set_message(t('db_insert failed. Message = %message, query= %query',
        array(
          '%message' => $e->getMessage(),
          '%query' => $e->query_string,
        )
      ), 'error');
    }
    return $return_value;
  }

  /**
   * Update an entry in the database.
   *
   * @param array $entry
   *   An array containing all the fields of the item to be updated.
   *
   * @return int
   *   The number of updated rows.
   *
   * @see db_update()
   */
  public static function update($entry) {
    $count = 0;
    try {
      // db_update()->execute() returns the number of rows updated.
      $count = db_update('content_direct')
        ->fields($entry)
        ->condition('cdid', $entry['cdid'])
        ->execute();
    }
    catch (\Exception $e) {
      drupal_set_message(t('db_update failed. Message = %message, query= %query',
        array(
          '%message' => $e->getMessage(),
          '%query' => $e->query_string,
        )
      ), 'error');
    }
    return $count;
  }

  /**
   * Delete an entry from the database.
   *
   * @param array $entry
   *   An array containing at least the unique identifier 'cdid' element of the
   *   entry to delete.
   *
   * @see db_delete()
   */
  public static function delete($entry) {
    db_delete('content_direct')
      ->condition('cdid', $entry['cdid'])
      ->execute();
  }

}
