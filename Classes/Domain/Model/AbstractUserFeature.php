<?php

namespace Blueways\BwGuild\Domain\Model;

use JetBrains\PhpStorm\ArrayShape;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use SourceBroker\T3api\Annotation as T3api;


/**
 * @T3api\ApiResource(
 *     collectionOperations={
 *          "get"={
 *              "path"="/userfeature",
 *              "normalizationContext"={
 *                  "groups"={"api_get_feature_collection_bwguild"}
 *              },
 *          },
 *           "post"={
 *              "method"="POST",
 *              "path"="/userfeature"
 *          },
 *     },
 *      itemOperations={
 *          "get"={
 *              "path"="/userfeature{id}",
 *              "normalizationContext"={
 *                  "groups"={"api_get_item_bwguild"}
 *              },
 *          },
 *          "patch"={
 *              "method"="PATCH",
 *              "path"="/userfeature{id}",
 *              "normalizationContext"={
 *                  "groups"={"api_patch_feature_item_bwguild"}
 *              },
 *          },
 *          "delete"={
 *              "method"="DELETE",
 *              "path"="/userfeature{id}",
 *          },
 *     },
 *     attributes = {
 *          "persistence"={
 *              "storagePid"="4",
 *              "recursive"=1
 *          }
 *     }
 * )
 *
 */
class AbstractUserFeature extends AbstractEntity
{
    /**
     * @var string
     * @T3api\Serializer\Groups({
     *     "api_get_item_bwguild",
     *     "api_get_collection_bwguild",
     *     "api_get_feature_collection_bwguild",
     *     "api_patch_feature_item_bwguild",
     * })
     */
    protected string $name = '';

    /**
     * @var string
     * @T3api\Serializer\Groups({
     *     "api_get_item_bwguild",
     *     "api_get_feature_collection_bwguild",
     *     "api_patch_feature_item_bwguild",
     * })
     */
    protected string $recordType = '';

    public function __construct()
    {
        $this->feUsers = new ObjectStorage();
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @param string $recordType
     */
    public function setRecordType(string $recordType): void
    {
        $this->recordType = $recordType;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getRecordType(): string
    {
        return $this->recordType;
    }

    #[ArrayShape(['label' => 'string', 'value' => 'int|null'])] public function getApiOutputArray(): array
    {
        return [
            'label' => $this->name,
            'value' => $this->getUid(),
        ];
    }
}
