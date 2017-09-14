<?php

namespace Sebdesign\VivaPayments\Test\Functional;

use Illuminate\Support\Carbon;
use Sebdesign\VivaPayments\Card;
use Sebdesign\VivaPayments\Order;
use Sebdesign\VivaPayments\Transaction;
use Sebdesign\VivaPayments\Test\TestCase;

class TransactionTest extends TestCase
{
    /**
     * @test
     * @group functional
     */
    public function createTransaction()
    {
        $orderCode = $this->getOrderCode();
        $token = $this->getToken();
        $installments = $this->getInstallments();

        $original = app(Transaction::class)->create([
            'OrderCode'       => $orderCode,
            'SourceCode'      => env('VIVA_SOURCE_CODE'),
            'Installments'    => $installments,
            'AllowsRecurring' => true,
            'CreditCard'      => [
                'Token'       => $token,
            ],
        ]);

        $this->assertAttributeEquals(Transaction::COMPLETED, 'StatusId', $original, 'The transaction was not completed.');
        $this->assertAttributeEquals(15, 'Amount', $original);

        return $original;
    }

    /**
     * @test
     * @group functional
     * @depends createTransaction
     */
    public function createRecurringTransaction($original)
    {
        $this->markTestSkipped('Error 400: Negative or zero amount');

        $installments = $this->getInstallments();

        $recurring = app(Transaction::class)->createRecurring($original->TransactionId, [
            'Amount'        => 1500,
            'SourceCode'    => env('VIVA_SOURCE_CODE'),
            'Installments'  => $installments,
        ]);

        $this->assertAttributeEquals(Transaction::COMPLETED, 'StatusId', $recurring, 'The transaction was not completed.');
        $this->assertAttributeEquals(15, 'Amount', $recurring);
    }

    /**
     * @test
     * @group functional
     * @depends createTransaction
     */
    public function getById($original)
    {
        $transactions = app(Transaction::class)->get($original->TransactionId);

        $this->assertNotEmpty($transactions);
        $this->assertCount(1, $transactions, 'There should be 1 transaction.');
        $this->assertAttributeEquals(Transaction::COMPLETED, 'StatusId', $transactions[0], 'The transaction was not completed.');
        $this->assertAttributeEquals($original->TransactionId, 'TransactionId', $transactions[0], "The transaction ID should be {$original->TransactionId}.");

        return $transactions[0];
    }

    /**
     * @test
     * @group functional
     * @depends getById
     */
    public function getByOrderCode($original)
    {
        $orderCode = $original->Order->OrderCode;

        $transactions = app(Transaction::class)->getByOrder($orderCode);

        $this->assertNotEmpty($transactions);

        foreach ($transactions as $key => $trns) {
            $this->assertAttributeEquals($orderCode, 'OrderCode', $trns->Order, "Transaction #{$key} should have order code {$orderCode}");
        }
    }

    /**
     * @test
     * @group functional
     * @depends getById
     */
    public function getByDate($original)
    {
        $date = Carbon::parse($original->InsDate);

        $transactions = app(Transaction::class)->getByDate($date);

        $this->assertNotEmpty($transactions);

        foreach ($transactions as $transaction) {
            $this->assertTrue(Carbon::parse($transaction->InsDate)->isSameDay($date));
        }
    }

    /**
     * @test
     * @group functional
     * @depends getById
     */
    public function getByClearanceDate($original)
    {
        $this->markTestSkipped('Clearance date is null.');

        $date = Carbon::parse($original->InsDate);

        $transactions = app(Transaction::class)->getByClearanceDate($date);

        $this->assertNotEmpty($transactions);

        foreach ($transactions as $key => $trns) {
            $this->assertTrue(Carbon::parse($trns->ClearanceDate)->isSameDay($date));
        }
    }

    /**
     * @test
     * @group functional
     * @depends createTransaction
     */
    public function cancelTransaction($original)
    {
        $transaction = app(Transaction::class);

        $response = $transaction->cancel($original->TransactionId, 1500);

        $this->assertAttributeEquals(Transaction::COMPLETED, 'StatusId', $response, 'The cancel transaction was not completed.');
        $this->assertAttributeEquals(15, 'Amount', $response);

        $transactions = $transaction->get($original->TransactionId);

        $this->assertNotEmpty($transactions);
        $this->assertCount(1, $transactions, 'There should be 1 transaction.');
        $this->assertAttributeEquals(Transaction::CANCELED, 'StatusId', $transactions[0], 'The original transaction should be canceled.');
        $this->assertAttributeEquals(15, 'Amount', $transactions[0]);
    }

    protected function getOrderCode()
    {
        return app(Order::class)->create(1500, [
            'CustomerTrns' => 'Test Transaction',
            'SourceCode' => env('VIVA_SOURCE_CODE'),
            'AllowRecurring' => true,
        ]);
    }

    protected function getToken()
    {
        $expirationDate = Carbon::parse('next year');

        return app(Card::class)->token('Customer name', '4111 1111 1111 1111', 111, $expirationDate->month, $expirationDate->year);
    }

    protected function getInstallments()
    {
        return app(Card::class)->installments('4111 1111 1111 1111');
    }
}
