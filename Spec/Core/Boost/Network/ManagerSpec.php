<?php

namespace Spec\Minds\Core\Boost\Network;

use Minds\Core\Boost\Network\Manager;
use Minds\Core\Boost\Network\Boost;
use Minds\Core\Boost\Network\Repository;
use Minds\Core\Boost\Network\ElasticRepository;
use Minds\Core\EntitiesBuilder;
use Minds\Core\GuidBuilder;
use Minds\Entities\Activity;
use Minds\Entities\User;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class ManagerSpec extends ObjectBehavior
{
    private $repository;
    private $elasticRepository;
    private $entitiesBuilder;
    private $guidBuilder;

    function let(
        Repository $repository,
        ElasticRepository $elasticRepository,
        EntitiesBuilder $entitiesBuilder,
        GuidBuilder $guidBuilder
    )
    {
        $this->beConstructedWith($repository, $elasticRepository, $entitiesBuilder, $guidBuilder);
        $this->repository = $repository;
        $this->elasticRepository = $elasticRepository;
        $this->entitiesBuilder = $entitiesBuilder;
        $this->guidBuilder = $guidBuilder;
    }

    function it_is_initializable()
    {
        $this->shouldHaveType(Manager::class);
    }

    function it_should_return_a_list_of_boosts_to_review()
    {
        $this->elasticRepository->getList([
            'state' => 'review',
            'hydrate' => true,
            'useElastic' => true,
        ])
            ->shouldBeCalled()
            ->willReturn([
                (new Boost)
                    ->setEntityGuid(123)
                    ->setImpressions(1000)
                    ->setOwnerGuid(1),
                (new Boost)
                    ->setEntityGuid(456)
                    ->setImpressions(100)
                    ->setOwnerGuid(2)
            ]);

        $this->entitiesBuilder->single(123)
            ->shouldBeCalled()
            ->willReturn((new Activity)
                ->set('guid', 123));

        $this->entitiesBuilder->single(1)
            ->shouldBeCalled()
            ->willReturn((new User)
                ->set('guid', 1));
         
        $this->entitiesBuilder->single(456)
            ->shouldBeCalled()
            ->willReturn((new Activity)
                ->set('guid', 456));

        $this->entitiesBuilder->single(2)
            ->shouldBeCalled()
            ->willReturn((new User)
                ->set('guid', 2));

        $response = $this->getList([
            'state' => 'review',
        ]);

        $response[0]->getEntity()->getGuid()
            ->shouldBe(123);
        $response[0]->getOwner()->getGuid()
            ->shouldBe(1);
        $response[0]->getImpressions()
            ->shouldBe(1000);

        $response[1]->getEntity()->getGuid()
            ->shouldBe(456);
        $response[1]->getOwner()->getGuid()
            ->shouldBe(2);
        $response[1]->getImpressions()
            ->shouldBe(100);
    }

    function it_should_return_a_list_of_boosts_to_deliver()
    {
        $this->elasticRepository->getList([
            'state' => 'approved',
            'hydrate' => true,
            'useElastic' => true,
        ])
            ->shouldBeCalled()
            ->willReturn([
                (new Boost)
                    ->setEntityGuid(123)
                    ->setImpressions(1000)
                    ->setOwnerGuid(1),
                (new Boost)
                    ->setEntityGuid(456)
                    ->setImpressions(100)
                    ->setOwnerGuid(2)
            ]);

        $this->entitiesBuilder->single(123)
            ->shouldBeCalled()
            ->willReturn((new Activity)
                ->set('guid', 123));

        $this->entitiesBuilder->single(1)
            ->shouldBeCalled()
            ->willReturn((new User)
                ->set('guid', 1));
         
        $this->entitiesBuilder->single(456)
            ->shouldBeCalled()
            ->willReturn((new Activity)
                ->set('guid', 456));

        $this->entitiesBuilder->single(2)
            ->shouldBeCalled()
            ->willReturn((new User)
                ->set('guid', 2));

        $response = $this->getList([
            'state' => 'approved',
            'useElastic' => true,
        ]);

        $response[0]->getEntity()->getGuid()
            ->shouldBe(123);
        $response[0]->getOwner()->getGuid()
            ->shouldBe(1);
        $response[0]->getImpressions()
            ->shouldBe(1000);

        $response[1]->getEntity()->getGuid()
            ->shouldBe(456);
        $response[1]->getOwner()->getGuid()
            ->shouldBe(2);
        $response[1]->getImpressions()
            ->shouldBe(100);
    }

    function it_should_return_a_list_of_boosts_from_guids()
    {
        $this->repository->getList([
            'state' => null,
            'guids' => [ 123, 456 ],
            'hydrate' => true,
            'useElastic' => false,
        ])
            ->shouldBeCalled()
            ->willReturn([
                (new Boost)
                    ->setEntityGuid(123)
                    ->setImpressions(1000)
                    ->setOwnerGuid(1),
                (new Boost)
                    ->setEntityGuid(456)
                    ->setImpressions(100)
                    ->setOwnerGuid(2)
            ]);

        $this->entitiesBuilder->single(123)
            ->shouldBeCalled()
            ->willReturn((new Activity)
                ->set('guid', 123));

        $this->entitiesBuilder->single(1)
            ->shouldBeCalled()
            ->willReturn((new User)
                ->set('guid', 1));
         
        $this->entitiesBuilder->single(456)
            ->shouldBeCalled()
            ->willReturn((new Activity)
                ->set('guid', 456));

        $this->entitiesBuilder->single(2)
            ->shouldBeCalled()
            ->willReturn((new User)
                ->set('guid', 2));

        $response = $this->getList([
            'guids' => [ 123, 456 ],
        ]);

        $response[0]->getEntity()->getGuid()
            ->shouldBe(123);
        $response[0]->getOwner()->getGuid()
            ->shouldBe(1);
        $response[0]->getImpressions()
            ->shouldBe(1000);

        $response[1]->getEntity()->getGuid()
            ->shouldBe(456);
        $response[1]->getOwner()->getGuid()
            ->shouldBe(2);
        $response[1]->getImpressions()
            ->shouldBe(100);
    }

    function it_should_add_a_boost(Boost $boost)
    {
        $this->guidBuilder->build()
            ->shouldBeCalled()
            ->willReturn(1);
    
        $boost->getGuid()
            ->shouldbeCalled()
            ->willReturn(null);

        $boost->setGuid(1)
            ->shouldBeCalled();

        $this->repository->add($boost)
            ->shouldBeCalled();
        $this->elasticRepository->add($boost)
            ->shouldBeCalled();

        $this->add($boost)
            ->shouldReturn(true);
    }

    function it_should_update_a_boost(Boost $boost)
    {
        $this->repository->update($boost, [ '@timestamp' ])
            ->shouldBeCalled();
        $this->elasticRepository->update($boost, [ '@timestamp' ])
            ->shouldBeCalled();

        $this->update($boost, [ '@timestamp'] );
    }
}
