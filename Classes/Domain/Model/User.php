<?php

namespace Blueways\BwGuild\Domain\Model;

use Blueways\BwGuild\Service\GeoService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Annotation\ORM\Lazy;
use TYPO3\CMS\Extbase\Domain\Model\Category;
use TYPO3\CMS\Extbase\Domain\Model\FrontendUser;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use SourceBroker\T3api\Annotation as T3api;
use SourceBroker\T3api\Filter\SearchFilter;
use SourceBroker\T3api\Filter\OrderFilter;
use SourceBroker\T3api\Annotation\Serializer\Exclude;
use SourceBroker\T3api\Annotation\ORM\Cascade;

/**
 * @T3api\ApiResource(
 *     collectionOperations={
 *          "get"={
 *              "path"="/users",
 *               "normalizationContext"={
 *                  "groups"={"api_get_collection_bwguild"}
 *              },
 *          },
 *          "post"={
 *              "method"="POST",
 *              "path"="/users",
 *              "normalizationContext"={
 *                  "groups"={"api_post_collection_bwguild"}
 *              },
 *          },
 *     },
 *     itemOperations={
 *          "get_current"={
 *              "path"="/users/current",
 *              "normalizationContext"={
 *                  "groups"={"api_get_item_bwguild"}
 *              },
 *          },
 *          "patch_current"={
 *              "method"="PATCH",
 *              "path"="/users/current",
 *              "normalizationContext"={
 *                  "groups"={"api_patch_item_bwguild"}
 *              },
 *          },
 *          "delete"={
 *              "method"="DELETE",
 *              "path"="/users{id}",
 *          }
 *     },
 *
 *     attributes={
 *          "persistence"={
 *              "storagePid"="4",
 *              "recursive"=1
 *          },
 *          "upload"={
 *              "folder"="1:/user_upload/",
 *              "allowedFileExtensions"={"jpg", "jpeg", "png"},
 *          }
 *     }
 * )
 *
 * @T3api\ApiFilter(
 *     SearchFilter::class,
 *     properties={"features.name":"partial"}
 * )
 *
 * @T3api\ApiFilter(
 *     SearchFilter::class,
 *     properties={"username":"partial"}
 * )
 *
 * @T3api\ApiFilter(
 *     SearchFilter::class,
 *     properties={"categories"}
 * )
 *
 * @T3api\ApiFilter(
 *     OrderFilter::class,
 *     properties={"uid","crdate","title"}
 * )
 *
 */
class User extends FrontendUser
{
    /**
     * @var string
     * @T3api\Serializer\Groups({
     *     "api_post_collection_bwguild",
     *     "api_get_collection_bwguild",
     *     "api_get_item_bwguild",
     *     "api_patch_item_bwguild",
     * })
     */
    protected $username = '';

    /**
     * @var string
     * @Exclude()
     */
    protected string $shortName = '';

    /**
     * @T3api\Serializer\Groups({
     *     "api_post_collection_bwguild",
     * })
     */
    protected string $passwordRepeat = '';

    /**
     * @var string
     * @T3api\Serializer\Groups({
     *     "api_post_collection_bwguild",
     * })
     */
    protected $password = '';

    /**
     * @var string
     * @T3api\Serializer\Groups({
     *     "api_post_collection_bwguild",
     *     "api_get_collection_bwguild",
     *     "api_get_item_bwguild",
     *     "api_patch_item_bwguild",
     * })
     */
    protected string $mobile = '';

    /**
     * @var string
     * @Exclude()
     */
    protected string $memberNr = '';

    /**
     * @var \DateTime|null
     * @Exclude("true")
     */
    protected ?\DateTime $tstamp = null;

    /**
     * @var ObjectStorage<Offer>|null
     * @Lazy
     * @T3api\ORM\Cascade("persist")
     * @T3api\Serializer\Groups({
     *     "api_get_collection_bwguild",
     *     "api_get_item_bwguild",
     *     "api_patch_item_bwguild",
     * })
     */
    protected ?ObjectStorage $offers;

    /**
     * @var ObjectStorage<AbstractUserFeature>|null
     * @Lazy
     * @T3api\Serializer\Groups({
     *     "api_get_collection_bwguild",
     *     "api_get_item_bwguild",
     *     "api_patch_item_bwguild",
     * })
     * @T3api\ORM\Cascade("persist")
     */
    protected ?ObjectStorage $features;

    /**
     * @var ObjectStorage<Category>|null
     * @Lazy
     * @T3api\Serializer\Groups({
     *     "api_get_collection_bwguild",
     *     "api_get_item_bwguild",
     *     "api_patch_item_bwguild",
     * })
     */
    protected ?ObjectStorage $categories;

    /**
     * @var float
     * @Exclude("true")
     */
    protected float $latitude = 0.0;

    /**
     * @var float
     * @Exclude("true")
     */
    protected float $longitude = 0.0;

    /**
     * @var ObjectStorage<Offer>
     * @Lazy
     * @T3api\Serializer\Groups({
     *     "api_get_collection_bwguild",
     *     "api_get_item_bwguild",
     *     "api_patch_item_bwguild",
     * })
     */
    protected $sharedOffers;


    /**
     * @var bool
     * @Exclude("true")
     */
    protected bool $publicProfile = true;

    public function getFrontendUser(): ?int
    {
        if ($this->$GLOBALS['TSFE']->fe_user->user['uid']) {
            $this->getBookmarks();
        }
        return null;
    }

    /**
     * @var string
     * @T3api\ORM\Cascade("persist")
     * @T3api\Serializer\Groups({
     *     "api_get_item_bwguild",
     *     "api_patch_item_bwguild",
     * })
     */
    protected string $bookmarks = '';

    public function getBookmarks(): string
    {
        return $this->bookmarks;
    }

    /**
     * @var string
     * @Exclude("true")
     */
    protected string $slug = '';

    public function getSlug(): string
    {
        return $this->slug;
    }

    /**
     * @var FileReference|null
     * @T3api\Serializer\Groups({
     *     "api_get_collection_bwguild",
     *     "api_get_item_bwguild",
     *     "api_patch_item_bwguild",
     * })
     */
    protected ?FileReference $logo = null;

    /**
     * @var ObjectStorage<FileReference>
     * @T3api\Serializer\Groups({
     *     "api_get_collection_bwguild",
     *     "api_get_item_bwguild",
     *     "api_patch_item_bwguild",
     * })
     */
    protected $image = null;

    public function getImage(): ObjectStorage
    {
        return $this->image;
    }

    public function setImage(ObjectStorage $image): void
    {
        $this->image = $image;
    }

    public function addImage(FileReference $image): void
    {
        $this->image->attach($image);
    }


    public function __construct(string $username = '', string $password = '')
    {
        parent::__construct($username, $password);

        $this->categories = new ObjectStorage();
        $this->offers = new ObjectStorage();
        $this->sharedOffers = new ObjectStorage();
        $this->features = new ObjectStorage();
    }

    public function getLogo(): ?FileReference
    {
        return $this->logo;
    }

    public function setLogo(?FileReference $logo): void
    {
        $this->logo = $logo;
    }

    public function isPublicProfile(): bool
    {
        return $this->publicProfile;
    }

    public function setPublicProfile(bool $publicProfile): void
    {
        $this->publicProfile = $publicProfile;
    }

    public function getPasswordRepeat(): string
    {
        return $this->passwordRepeat;
    }

    public function setPasswordRepeat(string $passwordRepeat): void
    {
        $this->passwordRepeat = $passwordRepeat;
    }

    public function getLatitude(): float
    {
        return $this->latitude;
    }

    public function setLatitude(float $latitude): void
    {
        $this->latitude = $latitude;
    }

    public function getSharedOffers(): ?ObjectStorage
    {
        return $this->sharedOffers;
    }

    public function setSharedOffers(ObjectStorage $sharedOffers): void
    {
        $this->sharedOffers = $sharedOffers;
    }

    public function getAllOffers(): ?ObjectStorage
    {
        $offers = $this->offers;
        if ($this->sharedOffers) {
            $offers->addAll($this->sharedOffers);
        }

        return $offers;
    }

    public function getLongitude(): float
    {
        return $this->longitude;
    }

    public function setLongitude(float $longitude): void
    {
        $this->longitude = $longitude;
    }

    public function getCategories(): ObjectStorage
    {
        return $this->categories;
    }

    public function setCategories(ObjectStorage $categories): void
    {
        $this->categories = $categories;
    }

    public function getOffers(): ?ObjectStorage
    {
        return $this->offers;
    }

    public function setOffers(ObjectStorage $offers): void
    {
        $this->offers = $offers;
    }

    public function getShortName(): string
    {
        return $this->shortName;
    }

    public function setShortName(string $shortName): void
    {
        $this->shortName = $shortName;
    }

    public function getMobile(): string
    {
        return $this->mobile;
    }

    public function setMobile(string $mobile): void
    {
        $this->mobile = $mobile;
    }

    public function getMemberNr(): string
    {
        return $this->memberNr;
    }

    public function setMemberNr(string $memberNr): void
    {
        $this->memberNr = $memberNr;
    }

    /**
     * @return \DateTime|null
     */
    public function getTstamp(): ?\DateTime
    {
        return $this->tstamp;
    }

    /**
     * @param \DateTime|null $tstamp
     */
    public function setTstamp(?\DateTime $tstamp): void
    {
        $this->tstamp = $tstamp;
    }

    public function geoCodeAddress()
    {
        $geocodingService = GeneralUtility::makeInstance(GeoService::class);
        $coords = $geocodingService->getCoordinatesForAddress(
            $this->getAddress(),
            $this->getZip(),
            $this->getCity(),
            $this->getCountry()
        );

        if (count($coords)) {
            $this->latitude = $coords['latitude'];
            $this->longitude = $coords['longitude'];
        }
    }

    public function getJsonSchema(array $settings)
    {
        $image = $settings['schema']['defaultImage'] ?: '';

        $schema = [
            '@context' => 'http://schema.org/',
            '@type' => 'LocalBusiness',
            'name' => $this->getCompany(),
            'description' => $this->getName(),
            'image' => $image,

            'address' => [
                '@type' => 'PostalAddress',
                'addressLocality' => $this->getCity(),
                'postalCode' => $this->getZip(),
                'streetAddress' => $this->getAddress(),
                'addressCountry' => $this->getCountry() === 'Deutschland' ? 'Germany' : '',
            ],
            'member' => [
                '@type' => 'Person',
                'familyName' => $this->getLastName(),
                'givenName' => $this->getFirstName(),
            ],
            'telephone' => $this->getTelephone(),
            'faxNumber' => $this->getFax(),
            'email' => $this->getEmail(),
            'url' => $this->getWww(),
        ];

        if ($this->getLogo()) {
            $schema['logo'] = $this->getLogo()->getOriginalResource()->getPublicUrl();
        }

        return $schema;
    }

    public function getFeatures(): ?ObjectStorage
    {
        return $this->features;
    }

    public function getFeaturesGroupedByRecordType(): array
    {
        $groupedFeatures = [];
        /** @var AbstractUserFeature $feature */
        foreach ($this->features as $feature) {
            $groupedFeatures[(int)$feature->getRecordType()] ??= new ObjectStorage();
            $groupedFeatures[(int)$feature->getRecordType()]->attach($feature);
        }
        return $groupedFeatures;
    }

    public function getFeaturesAsJsonGroupedByRecordType(): array
    {
        $groupedFeatures = $this->getFeaturesGroupedByRecordType();

        return array_map(function ($featureGroup) {
            $featureGroup = array_map(function ($feature) {
                return $feature->getApiOutputArray();
            }, [...$featureGroup]);

            return json_encode(array_values($featureGroup));
        }, $groupedFeatures);
    }

    public function setFeatures(ObjectStorage $features): void
    {
        $this->features = $features;
    }
}
