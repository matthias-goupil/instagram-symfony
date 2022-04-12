<?php

namespace App\Controller;

use App\Entity\Subscriber;
use App\Entity\User;
use App\Form\UserProfileType;
use App\Form\UserType;
use App\Repository\SubscriberRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * @Route("/user")
 */
class UserController extends AbstractController
{
    /**
     * @Route("/", name="app_user_index", methods={"GET"})
     */
    public function index(UserRepository $userRepository): Response
    {
        if($this->getUser()){
            return $this->render('user/index.html.twig', [
                'users' => $userRepository->findAll(),
            ]);
        }
        return $this->redirectToRoute("app_login");
    }

    /**
     * @Route("/new", name="app_user_new", methods={"GET", "POST"})
     */
    public function new(Request $request, UserRepository $userRepository,UserPasswordHasherInterface $passwordHasher): Response
    {
        if($this->getUser() == null){
            $user = new User();
            $form = $this->createForm(UserType::class, $user);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $user->setPassword($passwordHasher->hashPassword($user,$user->getPassword()))
                    ->setFollowerNumber(0)
                    ->setFollowNumber(0)
                    ->setPublicationNumber(0);
                $userRepository->add($user);
                return $this->redirectToRoute('app_home', [], Response::HTTP_SEE_OTHER);
            }

            return $this->renderForm('user/new.html.twig', [
                'user' => $user,
                'form' => $form,
            ]);
        }
        return $this->redirectToRoute("app_home");
    }

    /**
     * @Route("/{id}", name="app_user_show", methods={"GET"})
     */
    public function show(User $user,SubscriberRepository $subscriberRepository): Response
    {
        if($this->getUser()){
            return $this->render('user/show.html.twig', [
                'user' => $user,
                "isFollow" => $subscriberRepository->findOneBy([
                    "follow" => $this->getUser(),
                    "follower" => $user
                ])
            ]);
        }
        return $this->redirectToRoute("app_login");
    }

    /**
     * @Route("/{id}/edit", name="app_user_edit", methods={"GET", "POST"})
     */
    public function edit(Request $request, User $user, UserRepository $userRepository,SluggerInterface $slugger): Response
    {
        if($this->getUser() != null && $this->getUser()->getId() == $user->getId()){
            $form = $this->createForm(UserProfileType::class,$user);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $profilePicture = $form->get("profilePicture")->getData();
                if($profilePicture){
                    $originalFilename = pathinfo($profilePicture->getClientOriginalName(), PATHINFO_FILENAME);
                    // this is needed to safely include the file name as part of the URL
                    $newFilename = $user->getId().'.'.$profilePicture->guessExtension();
                    try {
                        $profilePicture->move(
                            'img/profile picture/',
                            $newFilename
                        );
                    } catch (FileException $e) {
                        // ... handle exception if something happens during file upload
                        dd("non");
                    }
                    $user->setProfilePicture('img/profile picture/'.$newFilename);
                }
                $userRepository->add($user);
                return $this->redirectToRoute('app_user_show', [
                    "id" => $user->getId()
                ], Response::HTTP_SEE_OTHER);
            }

            return $this->renderForm('user/edit.html.twig', [
                'user' => $user,
                'form' => $form,
            ]);
        }
        return $this->redirectToRoute("app_login");
    }

    /**
     * @Route("/delete/{id}", name="app_user_delete", methods={"POST"})
     */
    public function delete(Request $request, User $user, UserRepository $userRepository,SubscriberRepository $subscriberRepository): Response
    {
        if($this->getUser() != null && $this->getUser()->getId() == $user->getId()){
            if ($this->isCsrfTokenValid('delete'.$user->getId(), $request->request->get('_token'))) {
                $fileSystem = new Filesystem();

                if($user->getProfilePicture() != null){
                    if($fileSystem->exists($user->getProfilePicture()) && $fileSystem->exists("img/publications/".$user->getId()."/")){
                        $fileSystem->remove([$user->getProfilePicture(),"img/publications/".$user->getId()]);
                    }
                }
                if($fileSystem->exists("img/publications/".$user->getId()."/")){
                    $fileSystem->remove(["img/publications/".$user->getId()]);
                }
                $session = new Session();
                $session->invalidate();
                $userRepository->remove($user);
                return $this->redirectToRoute("app_logout",[]);
            }
            return $this->redirectToRoute('app_user_show', ["id" => $user->getId()], Response::HTTP_SEE_OTHER);
        }
        return $this->redirectToRoute("app_login");
    }

    /**
     * @Route("/follow/{id<\d+>}", name="app_user_follow")
     */
    public function follow(User $user,SubscriberRepository $subscriberRepository): Response
    {
        if($userConnect = $this->getUser()) {
            if($user->getId() != $userConnect->getId()){
                if($currentSuscribe = $subscriberRepository->findOneBy([
                    "follow" => $userConnect,
                    "follower" => $user
                ])){
                    $subscriberRepository->remove($currentSuscribe);
                }
                else{
                    $subscribe = new Subscriber();
                    $subscribe->setFollow($this->getUser());
                    $subscribe->setFollower($user);
                    $subscriberRepository->add($subscribe);
                }
            }
            return $this->redirectToRoute("app_user_show", [
                "id" => $user->getId()
            ]);
        }
        return $this->redirectToRoute("app_login");
    }
}
