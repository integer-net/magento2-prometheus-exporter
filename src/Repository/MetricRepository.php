<?php
declare(strict_types=1);

namespace RunAsRoot\PrometheusExporter\Repository;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Api\SearchResultsInterfaceFactory;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use RunAsRoot\PrometheusExporter\Api\Data\MetricInterface;
use RunAsRoot\PrometheusExporter\Api\MetricRepositoryInterface;
use RunAsRoot\PrometheusExporter\Model\Metric;
use RunAsRoot\PrometheusExporter\Model\MetricFactory;
use RunAsRoot\PrometheusExporter\Model\ResourceModel\MetricCollection;
use RunAsRoot\PrometheusExporter\Model\ResourceModel\MetricCollectionFactory;
use RunAsRoot\PrometheusExporter\Model\ResourceModel\MetricResource;

class MetricRepository implements MetricRepositoryInterface
{
    /**
     * @var MetricFactory
     */
    protected $metricFactory;

    /**
     * @var MetricResource
     */
    private $metricResource;

    /**
     * @var MetricCollectionFactory
     */
    private $collectionFactory;

    /**
     * @var SearchResultsInterfaceFactory
     */
    private $searchResultsFactory;

    public function __construct(
        MetricResource $metricResource,
        MetricFactory $metricFactory,
        MetricCollectionFactory $collectionFactory,
        SearchResultsInterfaceFactory $searchResultsFactory
    ) {
        $this->metricFactory = $metricFactory;
        $this->metricResource = $metricResource;
        $this->collectionFactory = $collectionFactory;
        $this->searchResultsFactory = $searchResultsFactory;
    }

    /**
     * @param MetricInterface $object
     *
     * @return MetricInterface
     * @throws CouldNotSaveException
     */
    public function save(MetricInterface $object): MetricInterface
    {
        try {
            /** @var Metric $object */
            $this->metricResource->save($object);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__($e->getMessage()));
        }

        return $object;
    }

    /**
     * @param int $id
     *
     * @return MetricInterface
     * @throws NoSuchEntityException
     */
    public function getById(int $id): MetricInterface
    {
        /** @var Metric $object */
        $object = $this->metricFactory->create();
        $this->metricResource->load($object, $id);
        if (!$object->getId()) {
            throw new NoSuchEntityException(__('Metric with id "%1" does not exist.', $id));
        }

        return $object;
    }

    /**
     * @param MetricInterface $object
     *
     * @return bool
     * @throws CouldNotDeleteException
     */
    public function delete(MetricInterface $object): bool
    {
        try {
            /** @var Metric $object */
            $this->metricResource->delete($object);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(__($exception->getMessage()));
        }

        return true;
    }

    /**
     * @param int $id
     *
     * @return bool
     * @throws CouldNotDeleteException
     * @throws NoSuchEntityException
     */
    public function deleteById(int $id): bool
    {
        $object = $this->getById($id);

        return $this->delete($object);
    }

    public function getList(SearchCriteriaInterface $criteria): SearchResultsInterface
    {
        /** @var MetricCollection $collection */
        $collection = $this->collectionFactory->create();
        foreach ($criteria->getFilterGroups() as $filterGroup) {
            $fields = [];
            $conditions = [];
            foreach ($filterGroup->getFilters() as $filter) {
                $condition = $filter->getConditionType() ?: 'eq';
                $fields[] = $filter->getField();
                $conditions[] = [$condition => $filter->getValue()];
            }
            if ($fields) {
                $collection->addFieldToFilter($fields, $conditions);
            }
        }
        $sortOrders = $criteria->getSortOrders();
        if ($sortOrders) {
            /** @var SortOrder $sortOrder */
            foreach ($sortOrders as $sortOrder) {
                $direction = ($sortOrder->getDirection() === SortOrder::SORT_ASC) ? 'ASC' : 'DESC';
                $collection->addOrder($sortOrder->getField(), $direction);
            }
        }
        $collection->setCurPage($criteria->getCurrentPage());
        $collection->setPageSize($criteria->getPageSize());

        $objects = [];
        foreach ($collection as $objectModel) {
            $objects[] = $objectModel;
        }

        /** @var SearchResultsInterface $searchResults */
        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($criteria);
        $searchResults->setTotalCount($collection->getSize());
        $searchResults->setItems($objects);

        return $searchResults;
    }
}