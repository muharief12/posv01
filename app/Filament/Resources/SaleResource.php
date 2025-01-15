<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SaleResource\Pages;
use App\Filament\Resources\SaleResource\RelationManagers;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleDetail;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Support\RawJs;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SaleResource extends Resource
{
    protected static ?string $model = Sale::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        $date = now()->format('Ymd');
        return $form
            ->schema([
                Section::make()
                    ->schema([
                        Forms\Components\Select::make('customer_id')
                            ->relationship('customer', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),
                        Forms\Components\DatePicker::make('date')
                            ->label(__('Date'))
                            ->native(false)
                            ->default(now())
                            ->live()
                            ->afterStateUpdated(function (Set $set, $state) {
                                $formattedDate = Carbon::parse($state)->format('Ymd');
                                $date = Carbon::parse($state);
                                $count = Sale::whereDate('date', $date->toDateString())->count() + 1;
                                $set('reference', "SL_$formattedDate" . str_pad($count, 3, "0", STR_PAD_LEFT));
                            })
                            ->required(),
                        Forms\Components\TextInput::make('reference')
                            ->default(fn() => "SL_$date" . str_pad(Sale::whereDate('date', $date)->count() + 1, 3, "0", STR_PAD_LEFT))
                            ->unique(ignoreRecord: true)
                            ->required()
                            ->maxLength(255),
                    ])->columns(3),
                Section::make()
                    ->schema([
                        Repeater::make('saleDetails')
                            ->label(__('Product'))
                            ->relationship()
                            ->schema([
                                Select::make('product_id')
                                    ->relationship('product', 'name')
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, $state, Get $get) {
                                        $product = Product::query()->find($state);
                                        if ($product) {
                                            $set('product_name', $product?->name);
                                            $set('product_code', $product?->code);
                                            $formattedPrice = number_format($product?->price, 0);
                                            $set('unit_price', $formattedPrice);
                                            $quantity = (int) str_replace(',', '', $get('quantity'));
                                            $total_price = $product?->price * $quantity;
                                            $formattedPrice = number_format($total_price, 0);
                                            $set('total_product_price', $formattedPrice);
                                        } else {
                                            $set('unit_price', 0);
                                            $set('quantity', 0);
                                            $set('total_product_price', 0);
                                            $set('product_name', "");
                                            $set('product_code', "0");
                                        }
                                    })
                                    ->preload()
                                    ->searchable()
                                    ->columnSpan(6),
                                TextInput::make('product_name')
                                    ->required()
                                    ->columnSpan(6),
                                TextInput::make('unit_price')
                                    ->label(__('Unit Price'))
                                    ->prefix('Rp')
                                    ->readOnly()
                                    ->mask(RawJs::make('$money($input)'))
                                    ->live()
                                    // ->dehydrateStateUsing(fn(string $state): string => (int) str_replace(',', '', $state))
                                    ->required()
                                    ->columnSpan(5),
                                TextInput::make('quantity')
                                    ->numeric()
                                    ->default(0)
                                    // ->live(debounce: 300)
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, $state, Get $get) {
                                        $unitPrice = (int) str_replace(',', '', $get('unit_price'));
                                        $total_price = $state * $unitPrice;
                                        $formattedPrice = number_format($total_price, 0);
                                        $set('total_product_price', $formattedPrice);
                                    })
                                    ->required()
                                    ->columnSpan(2),
                                TextInput::make('total_product_price')
                                    ->label(__('Total Price'))
                                    ->live()
                                    ->default(0)
                                    ->prefix('Rp')
                                    ->readOnly()
                                    ->mask(RawJs::make('$money($input)'))
                                    ->dehydrateStateUsing(fn(string $state): string => (int) str_replace(',', '', $state))
                                    ->required()
                                    ->columnSpan(5),
                                Hidden::make('product_code'),
                            ])
                            ->columns(12)
                            ->columnSpanFull()
                            ->live()
                            ->afterStateUpdated(function (Set $set, $state) {
                                // Hitung total dari semua total_product_price dalam repeater
                                $total = collect($state)->sum(function ($item) {
                                    return (int) str_replace(',', '', $item['total_product_price'] ?? 0);
                                });

                                // Format total ke dalam format angka dengan ribuan separator
                                $formattedTotal = number_format($total, 0);

                                // Set nilai field `total`
                                $set('total', $formattedTotal);
                            }),
                    ]),
                Section::make('Billing')
                    ->schema([
                        Forms\Components\TextInput::make('total')
                            ->disabled()
                            ->live()
                            ->prefix('Rp')
                            ->dehydrateStateUsing(function ($state, Get $get) {
                                // Mengambil nilai dari repeater
                                $saleDetails = $get('saleDetails') ?? [];

                                // Hitung total dari semua total_product_price
                                $total = collect($saleDetails)->sum(function ($item) {
                                    return (int) str_replace(',', '', $item['total_product_price'] ?? 0);
                                });

                                return number_format($total, 0); // Format angka dengan separator ribuan
                            })
                            ->nullable(),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('reference')
                    ->searchable(),
                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->color('info')
                    ->searchable()
                    ->badge()
                    ->getStateUsing(function ($record) {
                        // Menghitung total dari total_product_price yang terkait dengan saleDetails  
                        return "Rp " . number_format(SaleDetail::where('sale_id', $record->id)->sum('total_product_price'), 0) ?? 0;
                    }),
                Tables\Columns\TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSales::route('/'),
            'create' => Pages\CreateSale::route('/create'),
            'view' => Pages\ViewSale::route('/{record}'),
            'edit' => Pages\EditSale::route('/{record}/edit'),
        ];
    }
}
