<?php


namespace App\Http\Controllers;

use App\Bus;
use App\Bus_layout;
use App\Seat;
use App\Seat_info;
use App\Trip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use function Sodium\compare;

class RepActivityController extends Controller
{

    public function getBusList($id){

        $busname=DB::table('representatives')->where('username',$id)->value('enterprise');

        $buses=DB::table('buses')->where('name',$busname)
            ->join('bus_layouts','buses.id','bus_layouts.busID')
            ->select('buses.name','buses.coach_no','buses.type','buses.total_seat','buses.status','bus_layouts.id','bus_layouts.busID')
            ->get();
/*
        $buses=DB::table('buses')->where('name',$busname)
            ->select('buses.name','buses.coach_no','buses.type','buses.total_seat','buses.status','buses.rID','buses.id')
            ->get();
*/

        return view('representative.representative-buses')->with('buses',$buses);
    }

    public function getFilteredBusList(Request $request,$id){

        $busname=DB::table('representatives')->where('username',$id)->value('enterprise');
        $type = $request->get('type');
        $status = $request->get('status');
        $buses='';

        /*
                $buses=DB::table('buses')->where('name',$busname)
                    ->join('bus_layouts','buses.id','bus_layouts.busID')
                    ->select('buses.name','buses.coach_no','buses.type','buses.total_seat','buses.status','bus_layouts.id','buses.id')
                    ->get();
        */
        if($type=='All' && $status=='All') {
            $buses = DB::table('buses')->where('name', $busname)
                ->join('bus_layouts','buses.id','bus_layouts.busID')
                ->select('buses.name', 'buses.coach_no', 'buses.type', 'buses.total_seat', 'buses.status','bus_layouts.id','bus_layouts.busID')
                ->get();
        }
        else if($type=='All') {
            $buses = DB::table('buses')->where('name', $busname)
                ->where('buses.status',$status)
                ->join('bus_layouts','buses.id','bus_layouts.busID')
                ->select('buses.name', 'buses.coach_no', 'buses.type', 'buses.total_seat', 'buses.status','bus_layouts.id','bus_layouts.busID')
                ->get();
        }

        else if($status=='All') {
            $buses = DB::table('buses')->where('name', $busname)
                ->where('buses.type',$type)
                ->join('bus_layouts','buses.id','bus_layouts.busID')
                ->select('buses.name', 'buses.coach_no', 'buses.type', 'buses.total_seat', 'buses.status','bus_layouts.id','bus_layouts.busID')
                ->get();
        }
        else{
            $buses = DB::table('buses')->where('name', $busname)
                ->where('buses.status',$status)
                ->where('buses.type',$type)
                ->join('bus_layouts','buses.id','bus_layouts.busID')
                ->select('buses.name', 'buses.coach_no', 'buses.type', 'buses.total_seat', 'buses.status','bus_layouts.id','bus_layouts.busID')
                ->get();
        }

        return view('representative.representative-buses')->with('buses',$buses);
    }

    public function addNewBus($id){
        $busname=DB::table('representatives')->where('username',$id)->value('enterprise');

        return view('representative.representative-add-bus')->with('bus_name',$busname);
    }
    public function addNewBusPreview(Request $request,$id){

        $this->validate($request, [
            'bus_name' => 'required|string|max:255',
            'type' => 'required|string|max:255',
            'coach_no' => 'required|string|max:255|unique:buses',
        ]);

        $rID=DB::table('representatives')->where('username',$id)->value('id');

        $layout = json_decode($request->get("layout"));
        $layoutStr = '';
        $label = json_decode($request->get("label"));

        $rows = $request->get('rows');
       // if(is_int($rows)) echo "yes";
        for ($i=0;$i<$rows;$i++){
            for ($j=0;$j<6;$j++){
                $seatCategory = $layout[$i][$j];
                if($j==5)
                    $layoutStr = $layoutStr.$seatCategory.";";
                else
                    $layoutStr = $layoutStr.$seatCategory.",";
            }
        }
        //echo $layoutStr;
        //$layoutArr = explode(";",$layoutStr);
        //$layoutAr = explode(",",$layoutArr[0]);
        //echo "$layoutAr[2]";

        $bus = new Bus([
            'name' => $request->get('bus_name'),
            'coach_no' => $request->get('coach_no'),
            'type' => $request->get('type'),
            'status' => 'available',
            'total_seat' => $request->get('total_seat'),
            'available_seat' => $request->get('total_seat'),
            'rID' => $rID
        ]);
        $bus->save();

        $busID = DB::table('buses')->where('coach_no',$request->get('coach_no'))->value('id');
        $busLayout = new Bus_layout([
            'busID' => $busID,
            'decker_num' => $request->get('decker_num'),
            'rows' => $request->get('rows'),
            'columns' => $request->get('columns'),
            'layout' => $layoutStr
        ]);
        $busLayout->save();

        for ($i=0;$i<$rows;$i++){
            for ($j=0;$j<6;$j++){
                $seatLabel = $label[$i][$j];
                $seatCategory = $layout[$i][$j];

                if($seatLabel != "X"){
                    $seatInfo = new Seat_info([
                        'busID' => $busID,
                        'seatNo' => $seatLabel,
                        'status' => 'available',
                        'category' => $seatCategory
                    ]);
                    $seatInfo->save();
                }
            }
        }
        return view('representative.representative-add-bus')->with('bus_name',$request->get('bus_name'))->with('addMessage',"Successfully added.");
    }

    public function editBus(Request $request,$id,$busID){
        $var = DB::table('buses')->where('id',$busID)->value('coach_no');

        $type=$cno=$tseat=$status='';
        $type = $request->get('type');
        $cno = $request->get('coach_no');
        $tseat = $request->get('total_seat');
        $status = $request->get('status');
        if($var != $cno) {
            $this->validate($request, [
                'coach_no' => 'required|string|max:255|unique:buses',
            ]);
        }
//echo "$type $cno $tseat $status $busID";
        DB::table('buses')->where('id',$busID)
            ->update(['coach_no' => $cno, 'type' => $type, 'status' => $status, 'total_seat' => $tseat]);

        return redirect("representative-buses/".$id);

    }

    public function search_trips($id)
    {
        $bus=DB::table('representatives')->where('username',$id)->value('enterprise');

        $data=DB::table('trips')
            ->join('buses','trips.busID', '=', 'buses.id')->where('buses.name',$bus)
            ->join('routes','trips.routeID', '=', 'routes.id')
            ->select('routes.from','routes.to','routes.starting_point','buses.coach_no','buses.type','trips.date',
                'trips.departure_time','trips.b/e','trips.comment','trips.id')
            ->get();

        $send_data=(object)array(
            'bus' => $bus,
        );

        $places=DB::table('routes')->distinct()->select('to')->get();

        return View::make('representative.representative-trips')->with('searchdata',$data)
            ->with('send_data',$send_data)->with('places',$places);

    }

    public function editTrip(Request $request,$id,$tripID){

        $bus=DB::table('representatives')->where('username',$id)->value('enterprise');

        $cno = $request->get('coach_no');
        $busID = DB::table('buses')->where('coach_no',$cno)->where('name',$bus)->where('status','available')->value('id');

        if($busID == "")
            return back();

        $status = $request->get('status');
        DB::table('trips')->where('id',$tripID)
            ->update(['busID' => $busID, 'comment' => $status]);

        return redirect("representative-trips/".$id);

    }

    public function addNewTripForm($id){
        $busname=DB::table('representatives')->where('username',$id)->value('enterprise');

        $data = DB::table('routes')->select('to')->distinct()->get();
        $places = collect();
        $idx = 0;
        foreach ($data as $dt){
            foreach ($dt as $d)
                $places->put($idx,$d);
            $idx = $idx+1;
        }

        $from = json_encode($places);

        return view('representative.representative-add-trip')->with('bus_name',$busname)->with('from',$from);
    }

    public function addNewTrip(Request $request, $id){
        $busname=DB::table('representatives')->where('username',$id)->value('enterprise');

        $from = $request->get('from');
        $to = $request->get('to');
        $spoint = $request->get('starting_point');
        $type = $request->get('type');
        $coach_no = $request->get('coach_no');
        $efare = $request->get('efare');
        $bfare = $request->get('bfare');
        $date = $request->get('date');
        $deptt = $request->get('dept_time');
        $arrivet = $request->get('arr_time');

        $routeID=DB::table('routes')->where('from',$from)->where('to',$to)->value('id');
        if(!$routeID){
            //return to here route add
        }

        $busID = DB::table('buses')->where('coach_no',$coach_no)->where('name',$busname)->value('id');

        if(!$busID){
            return back();
        }
        $total = DB::table('buses')->where('id',$busID)->value('total_seat');
        DB::table('buses')->where('id',$busID)->update(['available_seat' => $total]);

        $rID=DB::table('representatives')->where('username',$id)->value('id');
        if($bfare != $efare)
            $efare = $efare+'/'+$bfare;
        else
            $efare = $efare+'/'+$efare;
        $trip = new Trip([
            'routeID' => $routeID,
            'departure_time' => $deptt,
            'arrival_time' => $arrivet,
            'date' => $date,
            'comment' => 'available',
            'busID' => $busID,
            'rID' => $rID,
            'b/e' => $efare
        ]);
        $trip->save();
        $tripID=DB::table('trips')->where('routeID',$routeID)->where('busID',$busID)->where('date',$date)->value('id');

        $seats = DB::table('seat_infos')->where('busID',$busID)
            ->select('id','category','status')->get();

        foreach ($seats as $seat){
            $idx=0;
            $seatID=$category=$status=$val='';
            foreach ($seat as $st){
                if($idx==0) $seatID=$st;
                else if($idx==1) $category=$st;
                else $status=$st;
                $idx++;
            }
            if($category=='Business')
                $val=$bfare;
            else
                $val=$efare;
            $data=new Seat([
                'tripID' => $tripID,
                'seatID' => $seatID,
                'fare' => $val,
                'status' => $status
            ]);
            $data->save();
        }


        return view('representative.representative-add-trip')->with('bus_name',$busname)
            ->with('addMessage','Trip has been added successfully');
    }

}