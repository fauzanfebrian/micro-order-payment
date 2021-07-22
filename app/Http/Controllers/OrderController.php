<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Midtrans\Config;
use Midtrans\Snap;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->input('user_id');
        $orders = Order::query();

        $orders->when($userId, function ($query) use ($userId) {
            return $query->where('user_id', '=', $userId);
        });

        return response()->json([
            'status' => 'succes',
            'data' => $orders->get(),
        ]);
    }

    public function create(Request $request)
    {
        $user = $request->input('user');
        $course = $request->input('course');
        if (!$user || !$course) {
            return response()->json([
                'status' => 'error',
                'message' => !$course ?
                (!$user ? 'user & course' : 'course') . ' data must be filled'
                : 'user data must be filled',
            ]);
        }
        $order = Order::create([
            'user_id' => $user['id'],
            'course_id' => $course['id'],
        ]);

        $midtransParams = [
            'transaction_details' => [
                'order_id' => $order->id . '-' . Str::random(5),
                'gross_amount' => $course['price'],
            ],
            'item_details' => [
                [
                    'id' => $course['id'],
                    'price' => $course['price'],
                    'quantity' => 1,
                    'name' => $course['name'],
                    'brand' => 'fauzan ,inc',
                    'category' => 'online course',
                ],
            ],
            'customer_details' => [
                'first_name' => $user['name'],
                'email' => $user['email'],
            ],
        ];

        $midtransSnapUrl = $this->getMidtransSnapUrl($midtransParams);

        $order->snap_url = $midtransSnapUrl;

        $order->metadata = [
            'course_id' => $course['id'],
            'course_price' => $course['price'],
            'course_name' => $course['name'],
            'course_thumbnail' => $course['thumbnail'],
            'course_level' => $course['level'],
        ];

        $order->save();

        return response()->json([
            'status' => 'success',
            'data' => $order,
        ]);
    }

    public function getMidtransSnapUrl($params)
    {
        Config::$serverKey = env("MIDTRANS_SERVER_KEY");
        Config::$isProduction = (bool) env('MIDTRANS_PRODUCTION');
        Config::$is3ds = (bool) env('MIDTRANS_3DS');

        $snapUrl = Snap::createTransaction($params)->redirect_url;
        return $snapUrl;
    }
}
