<?php

namespace Oro\Bundle\ActivityBundle\Form\DataTransformer;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\EntityManager;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Translation\TranslatorInterface;

use Oro\Bundle\ActivityBundle\Event\PrepareContextTitleEvent;
use Oro\Bundle\EntityBundle\Provider\EntityNameResolver;
use Oro\Bundle\EntityConfigBundle\Config\ConfigManager;

class ContextsToViewTransformer implements DataTransformerInterface
{
    /** @var EntityManager */
    protected $entityManager;

    /** @var ConfigManager */
    protected $configManager;

    /** @var TranslatorInterface */
    protected $translator;

    /* @var TokenStorageInterface */
    protected $securityTokenStorage;

    /** @var EventDispatcherInterface */
    protected $dispatcher;

    /** @var EntityNameResolver */
    protected $entityNameResolver;

    /** @var bool */
    protected $collectionModel;

    /**
     * @param EntityManager         $entityManager
     * @param ConfigManager         $configManager
     * @param TranslatorInterface   $translator
     * @param TokenStorageInterface $securityTokenStorage
     * @param EventDispatcherInterface $dispatcher
     * @param EntityNameResolver $entityNameResolver
     * @param bool $collectionModel True if result should be Collection instead of array
     */
    public function __construct(
        EntityManager $entityManager,
        ConfigManager $configManager,
        TranslatorInterface $translator,
        TokenStorageInterface $securityTokenStorage,
        EventDispatcherInterface $dispatcher,
        EntityNameResolver $entityNameResolver,
        $collectionModel = false
    ) {
        $this->entityManager        = $entityManager;
        $this->configManager        = $configManager;
        $this->translator           = $translator;
        $this->securityTokenStorage = $securityTokenStorage;
        $this->dispatcher           = $dispatcher;
        $this->entityNameResolver   = $entityNameResolver;
        $this->collectionModel      = $collectionModel;
    }

    /**
     * {@inheritdoc}
     */
    public function transform($value)
    {
        if (!$value) {
            return '';
        }

        if (is_array($value) || $value instanceof Collection) {
            $result = [];
            $user   = $this->securityTokenStorage->getToken()->getUser();
            foreach ($value as $target) {
                // Exclude current user
                $targetClass = ClassUtils::getClass($target);
                if (ClassUtils::getClass($user) === $targetClass &&
                    $user->getId() === $target->getId()
                ) {
                    continue;
                }

                $title = $this->entityNameResolver->getName($target);
                if ($label = $this->getClassLabel($targetClass)) {
                    $title .= ' (' . $label . ')';
                }

                $item['title'] = $title;
                $item['targetId'] = $target->getId();
                $event = new PrepareContextTitleEvent($item, $targetClass);
                $this->dispatcher->dispatch(PrepareContextTitleEvent::EVENT_NAME, $event);
                $item = $event->getItem();

                $result[] = json_encode($this->getResult($item['title'], $target));
            }

            $value = implode(';', $result);
        }

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function reverseTransform($value)
    {
        if (!$value) {
            return [];
        }

        $targets = explode(';', $value);
        $result  = [];
        $filters = [];

        foreach ($targets as $target) {
            $target = json_decode($target, true);
            if (array_key_exists('entityClass', $target) === true && array_key_exists('entityId', $target)) {
                if (!isset($filters[$target['entityClass']])) {
                    $filters[$target['entityClass']] = [];
                }
                $filters[$target['entityClass']][] = $target['entityId'];
            }
        }

        foreach ($filters as $entityClass => $ids) {
            $metadata = $this->entityManager->getClassMetadata($entityClass);
            $entities = $this->entityManager->getRepository($metadata->getName())->findBy(
                ['id' => $ids]
            );
            $result   = array_merge($result, $entities);
        }

        if ($this->collectionModel) {
            $result = new ArrayCollection($result);
        }

        return $result;
    }

    /**
     * @param string $className - FQCN
     *
     * @return string|null
     */
    protected function getClassLabel($className)
    {
        if (!$this->configManager->hasConfig($className)) {
            return null;
        }

        $label = $this->configManager->getProvider('entity')->getConfig($className)->get('label');

        return $this->translator->trans($label);
    }

    /**
     * @param string $text
     * @param object $object
     *
     * @return array
     */
    protected function getResult($text, $object)
    {
        return [
            'text' => $text,
            'id'   => json_encode([
                'entityClass' => ClassUtils::getClass($object),
                'entityId'    => $object->getId(),
            ])
        ];
    }
}
