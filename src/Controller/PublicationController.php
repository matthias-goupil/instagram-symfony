<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Like;
use App\Entity\Publication;
use App\Entity\User;
use App\Form\CommentFormType;
use App\Form\PublicationType;
use App\Repository\CommentRepository;
use App\Repository\LikeRepository;
use App\Repository\PublicationRepository;
use App\Repository\SubscriberRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/publication")
 */
class PublicationController extends AbstractController
{
    /**
     * @Route("/",name="app_home")
     */
    public function home(SubscriberRepository $subscriberRepository,PublicationRepository $publicationRepository,LikeRepository $likeRepository): Response{
        if($user = $this->getUser()){
            $followers = $subscriberRepository->findBy([
                "follow" => $user
            ]);
            $publications = [];
            foreach ($followers as $follower){
                foreach ($publicationRepository->findBy([
                    "user" => $follower->getFollower()
                ]) as $publication){
                    $publications[] = [$publication, $likeRepository->findOneBy([
                            "user" => $this->getUser(),
                            "publication" => $publication
                        ]) != null];
                }
            }
//            dd($publications);
            return $this->render("publication/home.html.twig",[
                "publications" => $publications
            ]);
        }
        return $this->redirectToRoute("app_login");
    }

    /**
     * @Route("/user/{id<\d+>}", name="app_publication_index", methods={"GET"})
     */
    public function index(User $user, PublicationRepository $publicationRepository): Response
    {
        if($this->getUser()){
            return $this->render('publication/index.html.twig', [
            'publications' => $publicationRepository->findBy([
                "user" => $user
            ])
            ]);
        }
        return $this->redirectToRoute("app_login");
    }

    /**
     * @Route("/new", name="app_publication_new", methods={"GET", "POST"})
     */
    public function new(Request $request, PublicationRepository $publicationRepository,CommentRepository $commentRepository): Response
    {
        if($user = $this->getUser()){
            $publication = new Publication();
            $form = $this->createForm(PublicationType::class, $publication);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $publication->setUser($user)
                    ->setLikeNumber(0)
                    ->setDate(new \DateTime("now"));

                $photoPublication = $form->get("photo")->getData();
                if($photoPublication){
                    // this is needed to safely include the file name as part of the URL
                    $newFilename = uniqid().'.'.$photoPublication->guessExtension();
                    try {
                        $photoPublication->move(
                            'img/publications/'.$user->getId(),
                            $newFilename
                        );
                    } catch (FileException $e) {
                        // ... handle exception if something happens during file upload
                    }
                    $publication->setPhoto('img/publications/'.$user->getId()."/".$newFilename);
                    $publicationRepository->add($publication);
                    $comment = new Comment();
                    $comment->setContent($form->get("comment")->getData());
                    $comment->setPublication($publication);
                    $comment->setUser($user);

                    $commentRepository->add($comment);

                    return $this->redirectToRoute("app_user_show",[
                        "id" => $user->getId()
                    ]);
                }
            }
            return $this->renderForm('publication/new.html.twig', [
                'publication' => $publication,
                'form' => $form,
            ]);
        }
        return $this->redirectToRoute("app_login");
    }

    /**
     * @Route("/{id}", name="app_publication_show", methods={"GET","POST"})
     */
    public function show(Request $request,Publication $publication,LikeRepository $likeRepository,CommentRepository $commentRepository): Response
    {
        if($this->getUser()){
            $comment = new Comment();
            $form = $this->createForm(CommentFormType::class,$comment);
            $form->handleRequest($request);

            if($form->isSubmitted() && $form->isValid()){
                $comment->setPublication($publication)
                    ->setUser($this->getUser());
                $commentRepository->add($comment);
            }

            return $this->renderForm('publication/show.html.twig', [
                'publication' => $publication,
                "user" => $publication->getUser(),
                "comments" => $commentRepository->findBy([
                    "publication" => $publication
                ]),
                "isLikeByUser" => $likeRepository->findOneBy([
                        "user" => $this->getUser(),
                        "publication" => $publication
                    ]) != null,
                "form" => $form
            ]);
        }
        return $this->redirectToRoute("app_login");
    }

    /**
     * @Route("/delete/{id}", name="app_publication_delete", methods={"POST"})
     */
    public function delete(Request $request, Publication $publication, PublicationRepository $publicationRepository): Response
    {
        if($this->getUser() != null && $this->getUser()->getId() == $publication->getUser()->getId()){
            if ($this->isCsrfTokenValid('delete'.$publication->getId(), $request->request->get('_token'))) {
                $publicationRepository->remove($publication);
                $fileSystem = new Filesystem();

                if($fileSystem->exists($publication->getPhoto())){
                    $fileSystem->remove([$publication->getPhoto()]);
                    return $this->redirectToRoute("app_user_show",["id" => $this->getUser()->getID()]);
                }
            }
            return $this->redirectToRoute('app_login', [], Response::HTTP_SEE_OTHER);
        }
        return $this->redirectToRoute("app_login");
    }

    /**
     * @Route("/like/{id<\d+>}", name="app_publication_like")
     */
    public function like(Request $request,Publication $publication,LikeRepository $likeRepository): Response{
        if($user = $this->getUser()){
            if($currentLike = $likeRepository->findOneBy([
                "user" => $user,
                "publication" => $publication
            ])){
                $likeRepository->remove($currentLike);
            }
            else{
                $like = new Like();
                $like->setUser($user)
                    ->setPublication($publication);
                $likeRepository->add($like);
            }
            $pagePrecedente = explode("/",$request->headers->get("referer"));
            if($pagePrecedente[count($pagePrecedente) - 2] == "publication" && $pagePrecedente[count($pagePrecedente) - 1] == "" ){
                return $this->redirect("/publication/#".$publication->getId());
            }
            else{
                return $this->redirectToRoute("app_publication_show",[
                    "id" => $publication->getId()
                ]);
            }

        }
        return $this->redirectToRoute("app_login");
    }
}
