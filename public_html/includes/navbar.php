<?php
require_once 'color_scheme.php';
require_once 'auth_middleware.php';

$user = authenticate();

if (!$user) {
    header("Location: login.php");
    exit();
}

$user_id = $user['user_id'];
$role = $user['role'];
$colorScheme = getColorScheme($role);

$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user_details = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<nav class="sb-topnav navbar navbar-expand <?php echo $colorScheme['navbar']; ?>">

    <!-- Left-aligned items -->

    <a class="navbar-brand ps-3" href="index.php">Plant Inventory</a>

    <button class="btn btn-link btn-sm order-1 order-lg-0 me-4 me-lg-0" id="sidebarToggle" href="#!"><i
            class="fas fa-bars"></i></button>



    <!-- Spacer to push the following items to the right -->

    <div class="d-none d-md-block flex-grow-1"></div>



    <!-- Right-aligned items -->

    <ul class="navbar-nav ms-auto me-0 me-md-3 my-2 my-md-0">

        <?php if ($user && $user['role'] !== 'admin'): ?>

            <?php

            // Fetch user balance (only for non-admin users)
        
            if ($user['role'] === 'pembeli') {

                $stmt = $pdo->prepare("SELECT balance FROM customers WHERE user_id = ?");

            } else {

                $stmt = $pdo->prepare("SELECT balance FROM petani WHERE user_id = ?");

            }

            $stmt->execute([$user['user_id']]);

            $userDetails = $stmt->fetch(PDO::FETCH_ASSOC);

            $balance = $userDetails['balance'] ?? 0;

            ?>

            <li class="nav-item">

                <span class="nav-link d-none d-md-inline-block">Saldo: Rp

                    <?php echo number_format($balance, 2, ',', '.'); ?></span>

            </li>

        <?php endif; ?>



        <?php if ($user && $user['role'] === 'pembeli'): ?>

            <li class="nav-item">

                <a class="nav-link" href="cart.php"><i class="fas fa-shopping-cart"></i><span
                        class="d-none d-md-inline-block ms-1"></span></a>

            </li>

        <?php endif; ?>



        <?php if ($user): ?>

            <li class="nav-item dropdown">

                <a class="nav-link dropdown-toggle" id="navbarDropdown" href="#" role="button" data-bs-toggle="dropdown"
                    aria-expanded="false"><i class="fas fa-user fa-fw"></i></a>

                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">

                    <?php if ($user['role'] === 'admin'): ?>

                        <li><a class="dropdown-item" href="logout.php">Logout</a></li>

                    <?php else: ?>

                        <?php if ($user['role'] === 'pembeli'): ?>

                            <li><a class="dropdown-item" href="profile.php">Profile</a></li>

                            <li><a class="dropdown-item" href="update_location.php">Ubah Lokasi</a></li>

                            <li><a class="dropdown-item" href="add_balance.php">Tambah Saldo</a></li>

                            <li><a class="dropdown-item d-md-none" href="cart.php">Keranjang</a></li>

                        <?php elseif ($user['role'] === 'petani'): ?>

                            <li><a class="dropdown-item" href="profile.php">Profile</a></li>

                            <li><a class="dropdown-item" href="update_location_petani.php">Ubah Lokasi</a></li>

                            <li><a class="dropdown-item" href="petani_inbox.php">Inbox</a></li>

                        <?php endif; ?>

                        <li>

                            <hr class="dropdown-divider" />

                        </li>

                        <li><a class="dropdown-item" href="logout.php">Logout</a></li>

                    <?php endif; ?>

                </ul>

            </li>

        <?php else: ?>

            <li class="nav-item">

                <a class="nav-link" href="login.php">Login</a>

            </li>

            <li class="nav-item">

                <a class="nav-link" href="register.php">Register</a>

            </li>

        <?php endif; ?>

    </ul>

</nav>



<style>
    .bg-light-green {
        background-color: #e8f5e9 !important
    }

    .bg-light-brown {
        background-color: #f5e0d3 !important
    }

    .bg-light-brown .nav-link,
    .bg-light-brown .navbar-brand,
    .bg-light-green .nav-link,
    .bg-light-green .navbar-brand {
        color: #333 !important
    }

    @media (max-width:768px) {
        .navbar-nav {
            flex-direction: row;
            align-items: center
        }

        .navbar-nav .nav-item {
            padding: 0 5px
        }

        .navbar-nav .dropdown {
            position: static
        }

        .navbar-nav .dropdown-menu {
            left: 0;
            right: 0;
            width: 100%;
            position: absolute
        }

        .navbar>.container-fluid {
            justify-content: space-between
        }

        .navbar-brand {
            margin-right: 0
        }

        #sidebarToggle {
            order: -1
        }
    }
</style>