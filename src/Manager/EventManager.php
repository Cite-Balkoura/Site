<?php

namespace App\Manager;

use App\Document\Common\Participation;
use App\Document\Common\Profile;
use App\Document\Event;
use App\Document\Common\Event as CommonEvent;
use App\Repository\Common\ParticipationRepository;
use Doctrine\ODM\MongoDB\DocumentManager;
use OldSound\RabbitMqBundle\RabbitMq\ProducerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Security\Core\Security;

class EventManager
{
    private SessionInterface $session;
    private Security $security;
    private DocumentManager $documentManager;
    private ProducerInterface $minecraftProducer;
    private ParticipationRepository $participationRepository;

    public function __construct(RequestStack $requestStack, Security $security, DocumentManager $documentManager, ProducerInterface $minecraftProducer, ParticipationRepository $participationRepository)
    {
        $this->session = $requestStack->getSession();
        $this->security = $security;
        $this->documentManager = $documentManager;
        $this->minecraftProducer = $minecraftProducer;
        $this->participationRepository = $participationRepository;
    }

    public function registerToEvent(Event $event): void
    {
        if (!$event->isAcceptRegistration())
            throw new HttpException(Response::HTTP_UNAUTHORIZED);

        if (!$commonEvent = $this->documentManager
            ->getRepository(CommonEvent::class)
            ->findOneBy(['id' => $event->getCommonId()]))
            throw new HttpException(Response::HTTP_INTERNAL_SERVER_ERROR);

        $discordId =$this->security->getUser()->getDiscordId();

        if (!$profile = $this->documentManager
            ->getRepository(Profile::class)
            ->findOneBy(['discordId' => $discordId]))
            throw new HttpException(Response::HTTP_INTERNAL_SERVER_ERROR);

        if ($this->participationRepository->getParticipation($profile->getId(), $event->getCommonId())) {
            $this->session->getFlashBag()->add('error', ['Inscription d??j?? enregistr??e', 'Il semblerait que vous ayez d??j?? ??t?? inscrit ?? cet ??v??nement. Pas d\'inqui??tude, elle a d??j?? ??t?? prise en compte.']);
            return;
        }

//        $participation = new Participation();
//        $participation->setEvent($commonEvent);
//        $participation->setProfile($profile);
//        $this->documentManager->persist($participation);
//        $this->documentManager->flush();

        $this->minecraftProducer->publish(json_encode(['type' => 'participation', 'discordId' => intval($discordId), 'event' => $commonEvent->getName()]));

        $this->session->getFlashBag()->add('success', ['Inscription envoy??e', 'Votre demande d\'inscription a ??t?? enregist??e !']);
    }

}