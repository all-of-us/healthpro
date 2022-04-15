<?php

namespace App\Controller;

use App\Entity\Notice;
use App\Form\NoticeType;
use App\Repository\NoticeRepository;
use App\Service\LoggerService;
use Doctrine\ORM\EntityManagerInterface;
use App\Audit\Log;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/admin/notices")
 */
class NoticeController extends BaseController
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em);
    }

    /**
     * @Route("/", name="admin_notices")
     */
    public function index(NoticeRepository $noticeRepository)
    {
        $notices = $noticeRepository->findBy([], ['id' => 'asc']);
        return $this->render('notice/index.html.twig', [
            'notices' => $notices,
        ]);
    }

    /**
     * @Route("/notice/{id}", name="admin_notice")
     */
    public function edit(NoticeRepository $noticeRepository, LoggerService $loggerService, Request $request, $id=null)
    {
        if ($id) {
            $notice = $noticeRepository->find($id);
            if (!$notice) {
                throw $this->createNotFoundException('Page notice not found.');
            }
        } else {
            $notice = new Notice();
            $notice->setStatus(true);
        }

        $form = $this->createForm(NoticeType::class, $notice, ['timezone' => $this->getSecurityUser()->getTimezone()]);

        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                if ($notice === null) {
                    $notice = $form->getData();
                    $this->em->persist($notice);
                    $this->em->flush();
                    $loggerService->log(Log::NOTICE_ADD, $notice->getId());
                    $this->addFlash('success', 'Notice added');
                } elseif ($request->request->has('delete')) {
                    $this->em->remove($notice);
                    $this->em->flush();
                    $loggerService->log(Log::NOTICE_DELETE, $notice->getId());
                    $this->addFlash('success', 'Notice removed');
                } else {
                    $this->em->persist($notice);
                    $this->em->flush();
                    $loggerService->log(Log::NOTICE_EDIT, $notice->getId());
                    $this->addFlash('success', 'Notice updated');
                }
                return $this->redirect($this->generateUrl('admin_notices'));
            } else {
                // Add a form-level error if there are none
                if (count($form->getErrors()) == 0) {
                    $form->addError(new FormError('Please correct the errors below'));
                }
            }
        }

        return $this->render('notice/edit.html.twig', [
            'notice' => $notice,
            'settings_return_url' => ($id === null)
                ? $this->generateUrl('admin_notices')
                : $this->generateUrl('admin_notice', ['id' => $id]),
            'form' => $form->createView()
        ]);
    }
}
