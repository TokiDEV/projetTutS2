<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Activity;
use Carbon\Carbon;
use Illuminate\Support\MessageBag;
use App\ActivityGroup;

/**
 * Class EdtController
 * @package App\Http\Controllers
 *
 * Les controllers font le lien entre les modèles et les vues. Je vais pas trop m'étendre là-dessus, vu qu'on a commencé
 * à aborder le sujet en cours.
 *
 * Créer un controller : php artisan make:controller NameofController
 *
 * Pour connaître leur application sur Laravel : https://laravel.com/docs/5.4/controllers
 */
class EdtController extends Controller
{

    private $user_activities;

    private $weeks = [
        'prev' => 0,
        'curr' => 0,
        'next' => 0
    ];
    private $years = [
        'prev' => -1,
        'curr' => -1,
        'next' => -1
    ];

    const NB_JOUR = 5;
    const NB_HORAIRE = 10;
    const MAXWEEK = 52;
    const MINWEEK = 1;
    //const DATES = ["", "Lundi", "Mardi", "Mercredi", "Jeudi", "Vendredi"];


    /**
     * On s'assure que l'utilisateur est connecté.
     * Un middleware est une sorte de règle vérifiant une condition.
     * Si la condition n'est pas vérifiée, on peut être redirigé ailleurs par exemple.
     *
     * Pour mieux comprendre, se référer à la documentation: https://laravel.com/docs/5.4/middleware
     */
    public function __construct()
    {
        $this->middleware('auth');
        setlocale(LC_TIME, 'fr_FR.utf8');
    }

    /**
     * Show the basic EDT.
     * @param $year
     * @param $week
     * @return \Illuminate\Http\Response
     */
    public function index($year = -1, $week = 0)
    {
        if (!isset($year) || !is_numeric($year) || $year < 0) {
            $year = intval(date("Y"));
        }
        if (!isset($week) || !is_numeric($week) || $week < self::MINWEEK || $week > self::MAXWEEK) {
            $week = intval(date("W"));
        }

        $this->years['curr'] = $year;
        $this->weeks['curr'] = $week;

        $this->setNextPrev();

        //$this->user_activities = \Auth::user()->activities;

        $this->user_activities = \Auth::user()->activities
            ->where('year', '=', $year)
            ->where('week', '=', $week);

        foreach ($this->user_activities as $activity) {
            $activity->carbonize();
        }
        $planning = $this->getPlanning();
        $weeks = $this->weeks;
        $years = $this->years;
        return view('edt', compact('planning', 'weeks', 'years')); // redirige à la vue
    }

    /**
     * Il s'agit d'avoir les bonnes dates lorsque l'on clique sur les semaines suivantes et précédentes
     */
    private function setNextPrev()
    {
        $this->weeks['prev'] = $this->weeks['curr'] - self::MINWEEK;
        $this->years['prev'] = $this->years['curr'];
        $this->weeks['next'] = $this->weeks['curr'] + self::MINWEEK;
        $this->years['next'] = $this->years['curr'];

        if ($this->weeks['curr'] == self::MINWEEK) {
            $this->weeks['prev'] += self::MAXWEEK;
            $this->years['prev'] -= self::MINWEEK;
        } else if ($this->weeks['curr'] == self::MAXWEEK) {
            $this->weeks['next'] -= self::MAXWEEK;
            $this->years['next'] += self::MINWEEK;
        }
    }

    /**
     * Le but sera de préconstruire le planning ici. Recherche de solutions techniques ?
     * @return string
     */
    public function getPlanning()
    {
        $planning = '<div id="agenda">';
        $planning .= '<div class="day h-1">Horaires';
        for ($row = 0; $row < self::NB_HORAIRE; $row++) {
            $horaire = $row + 8;
            $planning .= '<div class="day h-1">' . $horaire . 'h</div>';
        }
        $planning .= '</div>';
        $date = new Carbon();
        $date->setISODate($this->years['curr'],$this->weeks['curr']);
        $d0 = $date->startOfWeek();
        for ($col = 1; $col <= self::NB_JOUR; $col++) {
            $planning .= '<div class="day h-1">'. $d0->formatLocalized('%A %d %B');
            $d0->addDay();
            for ($row = 0; $row < self::NB_HORAIRE; $row++) {
                $horaire = $row + 8;
                $found = false;
                foreach ($this->user_activities as $activity) {
                    if ($activity->day === $col && $activity->getBeginHour() === $horaire) {
                        $diff = $activity->getEndHour() - $horaire;
                        $row += $diff - 1;
                        $planning .= '<div class="day h-' . $diff . '"><a class="fluid ui simple dropdown ' . $activity->task->color . ' button">
                                          <div class="menu">
                                                <div class="simple dropdown item"><i class="edit icon"></i>Modifier
                                                      <div class="menu">
                                                          <h3>Modifier ce créneau</h3>
                                                          <form class="ui form" method="post" action="">
                                                              <div class="field">
                                                                  <label>Tâche</label>
                                                                  <div class="ui selection dropdown">
                                                                      <input type="hidden" name="task">
                                                                      <i class="dropdown icon"></i>
                                                                      <div class="default text">Tâche souhaitée</div>
                                                                      <div class="menu">
                                                                          <div class="item" data-value="1">tâche1</div>
                                                                          <div class="item" data-value="2">tâche2</div>
                                                                      </div>
                                                                  </div>
                                                              </div>
                                                              <div class="field">
                                                                  <label>Salle</label>
                                                                  <div class="ui selection dropdown">
                                                                      <input type="hidden" name="room">
                                                                      <i class="dropdown icon"></i>
                                                                      <div class="default text">Salle souhaitée</div>
                                                                      <div class="menu">
                                                                          <div class="item" data-value="1">salleDispo1</div>
                                                                          <div class="item" data-value="2">salleDispo2</div>
                                                                      </div>
                                                                  </div>
                                                              </div>
                                                              <div class="field">
                                                                  <label>Médecins</label>
                                                                  <select multiple="" class="ui dropdown">
                                                                      <option value="">Sélectionnez un ou plusieurs médecins</option>
                                                                      <option value="AF">Vincent</option>
                                                                      <option value="AF">Philippe</option>
                                                                  </select>
                                                              </div>
                                                              <div class="field">
                                                                  <label>Jour</label>
                                                                  <input type="date" name="day">
                                                              </div>
                                                              <div class="field">
                                                                  <label>Heure de début</label>
                                                                  <input type="time" name="begin">
                                                              </div>
                                                              <div class="field">
                                                                  <label>Heure de fin</label>
                                                                  <input type="time" name="end">
                                                              </div>
                                                              <button class="ui button" type="submit">Modifier</button>
                                                          </form>
                                                      </div>
                                              </div>
                                              <div class="item"><i class="delete icon"></i>
                                                <form method="post" action="' . route('edt.delete') . '">
                                                  <input type="hidden" name="_token" value="' . csrf_token() . '">
                                                  <input type="hidden" name="id" value="' . $activity->id . '">
                                                  <button type="submit">Supprimer</button>
                                                 </form>
                                              </div>
                                          </div>
                                          <p>' . $activity->task->task . '</p>
                                          <p>' . $activity->users->implode('firstname', ', ') . '</p>
                                          <p>' . $activity->room->room . '</p>
                                      </a></div>';
                        $found = true;
                    }
                }
                if (!$found) $planning .= '<div class="day h-1"></div>';
            }
            $planning .= '</div>';
        }
        $planning .= '</div>';

        return $planning;
    }

    /**
     * @param Request $request
     *
     * https://laravel.com/docs/5.4/requests
     * https://laravel.com/docs/5.4/responses
     */
    public function create(Request $request)
    {

    }

    /**
     * @param Request $request
     */
    public function update(Request $request)
    {

    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function delete(Request $request)
    {
        ActivityGroup::where('activity_id', '=', $request->id)->delete();
        Activity::find($request->id)->delete();
        $message = new MessageBag();
        $message->add('success', "Créneau supprimé avec succès");
        return back()->with('message', $message);
    }
}