<?php

namespace Blueways\BwGuild\Domain\Model\Dto;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class BaseDemand extends AbstractEntity
{

    /**
     * @var array
     */
    protected $categories;

    /**
     * @var string
     */
    protected $categoryConjunction = '';

    /**
     * @var string
     */
    protected $search = '';

    /**
     * @var string
     */
    protected $searchFields = '';

    /**
     * @var string
     */
    protected $excludeSearchFields = '';

    /**
     * @var bool
     */
    protected $includeSubCategories = false;

    /**
     * @return string
     */
    public function getExcludeSearchFields(): string
    {
        return $this->excludeSearchFields;
    }

    /**
     * @param string $excludeSearchFields
     */
    public function setExcludeSearchFields(string $excludeSearchFields): void
    {
        $this->excludeSearchFields = $excludeSearchFields;
    }

    /**
     * @return bool
     */
    public function isIncludeSubCategories(): bool
    {
        return $this->includeSubCategories;
    }

    /**
     * @param bool $includeSubCategories
     */
    public function setIncludeSubCategories(bool $includeSubCategories): void
    {
        $this->includeSubCategories = $includeSubCategories;
    }

    /**
     * @return array
     */
    public function getCategories(): array
    {
        return $this->categories;
    }

    /**
     * @param array $categories
     */
    public function setCategories(array $categories): void
    {
        $this->categories = $categories;
    }

    /**
     * @return string
     */
    public function getCategoryConjunction(): string
    {
        return $this->categoryConjunction;
    }

    /**
     * @param string $categoryConjunction
     */
    public function setCategoryConjunction(string $categoryConjunction): void
    {
        $this->categoryConjunction = $categoryConjunction;
    }

    /**
     * @return string
     */
    public function getSearch(): string
    {
        return $this->search;
    }

    /**
     * @param string $search
     */
    public function setSearch(string $search): void
    {
        $this->search = $search;
    }

    /**
     * @param \Blueways\BwGuild\Domain\Model\Dto\BaseDemand
     */
    public function overrideDemand($demand)
    {
        foreach ($demand as $key => $value) {
            $this->_setProperty($key, $value);
        }
    }

    public function getSearchFields()
    {
        $searchFields = GeneralUtility::trimExplode(',', $this->searchFields, true);
        $excludeSearchFields = GeneralUtility::trimExplode(',', $this->excludeSearchFields, true);

        $searchFields = array_diff($searchFields, $excludeSearchFields);

        return $searchFields;
    }

}
