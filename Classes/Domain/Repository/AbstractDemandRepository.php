<?php

namespace Blueways\BwGuild\Domain\Repository;

use Blueways\BwGuild\Domain\Model\Dto\BaseDemand;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

class AbstractDemandRepository extends Repository
{

    /**
     * @var \TYPO3\CMS\Core\Database\Query\QueryBuilder $queryBuilder
     */
    protected $queryBuilder;

    /**
     * @param \Blueways\BwGuild\Domain\Model\Dto\BaseDemand $demand
     * @return int
     */
    public function countDemanded($demand): int
    {
        $records = $this->findDemanded($demand);
        return $records->count();
    }

    /**
     * @param \Blueways\BwGuild\Domain\Model\Dto\BaseDemand $demand
     * @return array|\TYPO3\CMS\Extbase\Persistence\QueryResultInterface
     */
    public function findDemanded($demand)
    {
        /** @var \TYPO3\CMS\Core\Database\Query\QueryBuilder $queryBuilder */
        $this->queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($demand::TABLE);
        $this->queryBuilder->select('*');
        $this->queryBuilder->from($demand::TABLE);

        $this->setSearchFilterConstraints($demand);
        $this->setCategoryConstraints($demand);
        $this->setOrderConstraints($demand);
        $this->setLimitConstraint($demand);

        return $this->queryBuilder->execute()->fetchAll();
    }

    /**
     * @param BaseDemand $demand
     */
    private function setSearchFilterConstraints($demand): void
    {
        if (empty($demand->getSearch())) {
            return;
        }

        $constraints = [];
        $searchSplittedParts = $demand->getSearchParts();

        $tcaSearchFields = $GLOBALS['TCA'][$demand::TABLE]['ctrl']['searchFields'];
        $searchFields = GeneralUtility::trimExplode(',', $tcaSearchFields, true);

        foreach ($searchSplittedParts as $searchSplittedPart) {

            $subConstraints = [];

            foreach ($searchFields as $cleanProperty) {
                $searchSplittedPart = trim($searchSplittedPart);
                if ($searchSplittedPart) {
                    $subConstraints[] = $this->queryBuilder->expr()->like(
                        $cleanProperty,
                        $this->queryBuilder->createNamedParameter('%' . $searchSplittedPart . '%')
                    );
                }
            }
            $constraints[] = $this->queryBuilder->expr()->orX(...$subConstraints);
        }

        $this->queryBuilder->andWhere($this->queryBuilder->expr()->andX(...$constraints));
    }

    /**
     * @param \Blueways\BwGuild\Domain\Model\Dto\BaseDemand $demand
     */
    private function setCategoryConstraints(BaseDemand $demand): void
    {
        $categories = $demand->getCategories();
        $categoryConjunction = $demand->getCategoryConjunction();

        // abort if no category settings
        if (!count($categories) || !$categoryConjunction) {
            return;
        }

        switch (strtolower($categoryConjunction)) {
            case 'or':
                // join tables
                $this->queryBuilder->join(
                    $demand::TABLE,
                    'sys_category_record_mm',
                    'c',
                    $this->queryBuilder->expr()->eq('c.uid_foreign',
                        $this->queryBuilder->quoteIdentifier($demand::TABLE . '.uid'))
                );

                // any match
                $this->queryBuilder->andWhere(
                    $this->queryBuilder->expr()->in('c.uid_local', $categories)
                );
                break;
            case 'notor':
                // join tables
                $this->queryBuilder->join(
                    $demand::TABLE,
                    'sys_category_record_mm',
                    'c',
                    $this->queryBuilder->expr()->eq('c.uid_foreign',
                        $this->queryBuilder->quoteIdentifier($demand::TABLE . '.uid'))
                );

                // not any match
                $this->queryBuilder->andWhere(
                    $this->queryBuilder->expr()->notIn('c.uid_local', $categories)
                );
                break;
            case 'notand':
                // join for every category - include check for category uid in join statement
                foreach ($categories as $key => $category) {
                    $this->queryBuilder->join(
                        $demand::TABLE,
                        'sys_category_record_mm',
                        'c' . $key,
                        $this->queryBuilder->expr()->andX(
                            $this->queryBuilder->expr()->eq('c' . $key . '.uid_foreign',
                                $this->queryBuilder->quoteIdentifier($demand::TABLE . '.uid')),
                            $this->queryBuilder->expr()->neq('c' . $key . '.uid_local',
                                $this->queryBuilder->createNamedParameter($category, \PDO::PARAM_INT))
                        )
                    );
                }
                break;
            case 'and':
            default:
                // join for every category - include check for category uid in join statement
                foreach ($categories as $key => $category) {
                    $this->queryBuilder->join(
                        $demand::TABLE,
                        'sys_category_record_mm',
                        'c' . $key,
                        $this->queryBuilder->expr()->andX(
                            $this->queryBuilder->expr()->eq('c' . $key . '.uid_foreign',
                                $this->queryBuilder->quoteIdentifier($demand::TABLE . '.uid')),
                            $this->queryBuilder->expr()->eq('c' . $key . '.uid_local',
                                $this->queryBuilder->createNamedParameter($category, \PDO::PARAM_INT))
                        )
                    );
                }
        }

        // make result distinct
        $this->queryBuilder->groupBy('uid');
    }

    /**
     * @param \Blueways\BwGuild\Domain\Model\Dto\BaseDemand $demand
     */
    private function setOrderConstraints(BaseDemand $demand): void
    {
        $orderField = $demand->getOrder() ?: 'crdate';
        $orderFields = GeneralUtility::trimExplode(',', $orderField, true);
        $orderDirection = $demand->getOrderDirection() ?: QueryInterface::ORDER_ASCENDING;

        foreach ($orderFields as $orderField) {
            $this->queryBuilder->addOrderBy($orderField, $orderDirection);
        }
    }

    /**
     * @param \Blueways\BwGuild\Domain\Model\Dto\BaseDemand $demand
     */
    private function setLimitConstraint(BaseDemand $demand): void
    {
        if ($demand->getLimit() && $demand->getLimit() !== '' && $limit = (int)$demand->getLimit() > 0) {
            $this->queryBuilder->setMaxResults($limit);
        }
    }

    /**
     * Create Demand by settings array (see typoscript constants)
     *
     * @param $settings
     * @param string $class
     * @return \Blueways\BwGuild\Domain\Model\Dto\BaseDemand|mixed
     */
    public function createDemandObjectFromSettings(
        $settings,
        $class = BaseDemand::class
    ) {
        // @TODO: check if this typoscript demandClass setting makes sense
        $class = isset($settings['demandClass']) && !empty($settings['demandClass']) ? $settings['demandClass'] : $class;

        /** @var \Blueways\BwGuild\Domain\Model\Dto\BaseDemand $demand */
        $demand = $this->objectManager->get($class);

        $demand->setCategories(GeneralUtility::trimExplode(',', $settings['categories'], true));
        $demand->setCategoryConjunction($settings['categoryConjunction'] ?? '');
        $demand->setIncludeSubCategories($settings['includeSubCategories'] ?? false);
        $demand->setLimit($settings['limit'] ?? -1);
        $demand->setOrder($settings['order'] ?? '');
        $demand->setOrderDirection($settings['orderDirection'] ?? '');

        return $demand;
    }
}
