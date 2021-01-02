<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Redis;
use LaravelZero\Framework\Commands\Command;
use PHPExperts\ConciseUuid\ConciseUuid;

class PlayBigRaffleAlways extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'raffle:big {--players=10000}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Simulate playing a big raffle with 10,000+ people.';

    const MAX_RAFFLE_TICKETS_PER_PERSON = 3;

    public function generateRaffleTickets(int $number): array
    {
        $raffleTickets = [];
        for ($a = 0; $a <= $number; ++$a) {
            $raffleTickets[] = ConciseUuid::generateNewId();
        }

        return $raffleTickets;
    }

    public function buyRaffleTickets(array &$availableTickets, int $quantity)
    {
        if ($quantity > static::MAX_RAFFLE_TICKETS_PER_PERSON) {
            $max = static::MAX_RAFFLE_TICKETS_PER_PERSON;
            throw new \InvalidArgumentException("You cannot buy $quantity tickets. The max is $max.");
        }

        $myTickets = array_rand(array_flip($availableTickets), $quantity);
//dd($myTickets);
        return !is_array($myTickets) ? [$myTickets] : $myTickets;
    }

    public function drawRaffleTicket(array &$raffleTickets): string
    {
        shuffle($raffleTickets);

        return array_pop($raffleTickets);
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $play = function() {
            $numberOfPlayers = $this->option('players');

            // Minimum 1; Maximum: 3.
            $ticketsPerPlayer = random_int(100, static::MAX_RAFFLE_TICKETS_PER_PERSON * 100) / 100;

            // Generate enough raffle tickets for everyone.
            $numberOfTickets = ceil($numberOfPlayers * $ticketsPerPlayer);
            $pricePerTicket = 1;
            $gamePot = $pricePerTicket * $numberOfTickets;
            $availableRaffleTickets = $this->generateRaffleTickets($numberOfTickets);

            $numberOfWinners = floor(($gamePot * 0.90) / 1000);

            //$oddsOfWinning = 1 / ($numberOfWinners / $numberOfTicke
            //ts);
//            $numberOfTickets = 1000;
            $numberOfMyTickets = 3;
//            $numberOfWinners = 5;
            $oddsOfWinning = 1 / (1 / (1 + ($numberOfTickets / $numberOfWinners)));
//            $numberOfMyTickets = 3;
            $myTickets = $this->buyRaffleTickets($availableRaffleTickets, $numberOfMyTickets);
            //$myOddsOfWinning = $oddsOfWinning / $numberOfMyTickets;
            $myOddsOfWinning = 1 / ($numberOfMyTickets / ($numberOfMyTickets + ($numberOfTickets / $numberOfWinners)));
            echo "$numberOfMyTickets / ($numberOfMyTickets + ($numberOfTickets / $numberOfWinners)) = $myOddsOfWinning\n";
//            exit;

            $this->line("There are $numberOfTickets in play with a total of $numberOfWinners winners out of $numberOfPlayers players.");
            $this->line("The odds of any ticket winning are 1 in $oddsOfWinning.");
            $this->line("Since you have $numberOfMyTickets, your odds of winning are 1 in $myOddsOfWinning.");
//            $this->line("\nYour tickets are: " . implode(", ", $myTickets));

//            $this->ask('Begin?');
            for ($raffleNo = 1; $raffleNo <= $numberOfWinners; ++$raffleNo) {
//                $this->line("\nDrawing Raffle Ticket #$raffleNo of $numberOfTickets");

                $drawnTicket = $this->drawRaffleTicket($availableRaffleTickets);
                $raffleCount = count($availableRaffleTickets);
//                $this->line("The drawn ticket is $drawnTicket (of $raffleCount)");

                if (in_array($drawnTicket, $myTickets)) {
                    $this->line("HURRAY!!! You just won $1,000!!");

//                    throw new \Exception("Won @ #$drawnTicket pick~!!");
                    return $raffleNo;
                }
            }

            return null;
        };

        $numberOfGames = 1000;
        if (true) {
            for ($a = 1; $a <= 10; ++$a) {
                Redis::set("actualities-$a", json_encode([]));
                $pid = pcntl_fork();
                // This is a child process. Give it a job.
                if ($pid === 0) {
                    $won = json_decode(Redis::get("actualities-$a"), true) ?? [];

                    for ($b = 1; $b <= $numberOfGames / 10; ++$b) {
                        $game = $a * $b;
                        $this->line("Playing Game $game...");
                        $drawingsTilWon = $play();
                        if ($drawingsTilWon !== null) {
                            $won[] = $game;
                            Redis::set("actualities-$a", json_encode($won));
                            $this->line("Game $game won on drawing #$drawingsTilWon.");
                        } else {
//                            $this->line("Game $game was lost.");
                        }

//                    sleep(1);

//                        if (count($won) >= 10) {
//                            break;
//                        }
                    }

//                Redis::set("actualities-$a", json_encode($won));
                    dd($won);
                    exit;
                }
            }

            while (pcntl_waitpid(0, $status) != -1) ;
        }
        // Rebuild the actualities...
        $actualities = [];
        for ($a = 1; $a <= 10; ++$a) {
            $results = json_decode(Redis::get("actualities-$a"), true);
            if (is_array($results)) {
                $actualities = array_merge($actualities, $results);
            }
        }

        print_r($actualities);
        $numberOfGamesWon = count($actualities);
        $moneyWon = $numberOfGamesWon * 1000;
        $actualOdds = 1 / ($numberOfGamesWon / 1000);
        $profit = $moneyWon - (3 * 1000);
        $this->line("You won $numberOfGamesWon games out of $numberOfGames (\$$moneyWon (\$$profit profit)).");
        $this->line("Actual odds: 1 in $actualOdds.");
    }

    /**
     * Define the command's schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule $schedule
     * @return void
     */
    public function schedule(Schedule $schedule)
    {
        // $schedule->command(static::class)->everyMinute();
    }
}
