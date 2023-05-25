<?php
declare(strict_types=1);

namespace Blueways\BwGuild\Domain\Model;

use SourceBroker\T3api\Annotation as T3api;
use TYPO3\CMS\Core\Resource\DuplicationBehavior;

/**
 * @T3api\ApiResource (
 *     collectionOperations={
 *          "post"={
 *              "path"="/files",
 *              "method"="POST",
 *          },
 *     },
 *     attributes={
 *          "upload"={
 *              "folder"="1:/user_upload/",
 *              "allowedFileExtensions"={"jpg", "jpeg", "png"},
 *              "conflictMode"=DuplicationBehavior::RENAME,
 *          },
 *          "persistence"={
 *              "storagePid"="4",
 *              "recursive"=1
 *          }
 *     }
 * )
 */
class File extends \TYPO3\CMS\Extbase\Domain\Model\File
{
}
