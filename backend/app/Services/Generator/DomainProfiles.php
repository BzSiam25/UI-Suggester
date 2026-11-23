<?php

namespace App\Services\Generator;

/**
 * Domain-specific copy used by the template engine, so a "pharmacy app"
 * looks like a pharmacy and a "crop marketplace" looks like agriculture.
 */
class DomainProfiles
{
    /** Words that hint at several domains, so they count for less. */
    private const WEAK = ['delivery', 'booking', 'marketplace', 'product', 'store', 'sell', 'buy', 'payment', 'team', 'admin', 'portal', 'network', 'training', 'class', 'care'];

    public const PROFILES = [
        'ecommerce' => [
            'keywords' => ['shop', 'store', 'ecommerce', 'e-commerce', 'fashion', 'clothing', 'marketplace', 'retail', 'sell', 'buy', 'product'],
            'brand' => ['Cartly', 'Shopnest', 'Bazaario'],
            'tagline' => 'Everything you love, delivered to your door',
            'sub' => 'Discover thousands of curated products with fast delivery, easy returns and secure checkout.',
            'cta' => 'Start shopping',
            'nav' => ['Shop', 'New in', 'Deals', 'Brands'],
            'features' => [['🚚', 'Fast delivery', 'Same-day shipping in major cities.'], ['↩️', 'Easy returns', '30-day no-questions returns.'], ['🔒', 'Secure payments', 'Cards, mobile wallets and COD.']],
            'items' => [['Classic Denim Jacket', '$59'], ['Leather Sneakers', '$84'], ['Linen Summer Shirt', '$32'], ['Canvas Backpack', '$45'], ['Wireless Earbuds', '$69'], ['Ceramic Mug Set', '$24']],
            'chips' => ['All', 'Men', 'Women', 'Kids', 'Home', 'Electronics'],
            'stats' => [['Revenue', '$48.2k', '+12%'], ['Orders', '1,284', '+8%'], ['Customers', '932', '+5%'], ['Refunds', '14', '-3%']],
            'tableHead' => ['Order', 'Customer', 'Amount', 'Status'],
            'entity' => 'order',
        ],
        'food' => [
            'keywords' => ['food', 'restaurant', 'delivery', 'recipe', 'cafe', 'meal', 'kitchen', 'grocery', 'pizza', 'burger'],
            'brand' => ['Forkly', 'Biteway', 'Tiffin'],
            'tagline' => 'Hot meals from your favourite kitchens',
            'sub' => 'Order from hundreds of local restaurants and track your rider in real time.',
            'cta' => 'Order now',
            'nav' => ['Restaurants', 'Offers', 'Grocery', 'Track order'],
            'features' => [['⏱️', '30-min delivery', 'Riders near you, always.'], ['🎁', 'Daily offers', 'Save on every order.'], ['⭐', 'Top rated', 'Only kitchens rated 4+.']],
            'items' => [['Spicy Chicken Biryani', '$7.50'], ['Margherita Pizza', '$9.90'], ['Beef Smash Burger', '$8.40'], ['Thai Green Curry', '$8.80'], ['Mango Lassi', '$2.50'], ['Caesar Salad', '$6.20']],
            'chips' => ['All', 'Biryani', 'Pizza', 'Burgers', 'Desserts', 'Healthy'],
            'stats' => [['Orders today', '842', '+18%'], ['Avg. delivery', '27 min', '-4%'], ['Active riders', '126', '+6%'], ['Rating', '4.8', '+0.1']],
            'tableHead' => ['Order', 'Restaurant', 'Total', 'Status'],
            'entity' => 'order',
        ],
        'health' => [
            'keywords' => ['health', 'doctor', 'clinic', 'hospital', 'medical', 'pharmacy', 'medicine', 'patient', 'telemedicine', 'dental', 'healthcare'],
            'brand' => ['Caregrid', 'MediNest', 'Pulsewell'],
            'tagline' => 'Care that fits around your life',
            'sub' => 'Book appointments, consult doctors online and keep every prescription in one place.',
            'cta' => 'Book a consultation',
            'nav' => ['Doctors', 'Specialties', 'Pharmacy', 'Lab tests'],
            'features' => [['🩺', 'Verified doctors', '500+ licensed specialists.'], ['💬', 'Video consults', 'See a doctor in 10 minutes.'], ['💊', 'Medicine delivery', 'Prescriptions at your door.']],
            'items' => [['Dr. Amina Rahman — Cardiology', '$25'], ['Dr. Kenji Mori — Dermatology', '$20'], ['Full Blood Count', '$12'], ['Dr. Lena Ortiz — Pediatrics', '$18'], ['Vitamin D Test', '$15'], ['Dr. Omar Faruk — General', '$10']],
            'chips' => ['All', 'Cardiology', 'Dental', 'Skin', 'Child', 'Lab'],
            'stats' => [['Appointments', '214', '+9%'], ['Patients', '1,902', '+4%'], ['Avg. wait', '11 min', '-6%'], ['Satisfaction', '96%', '+1%']],
            'tableHead' => ['Patient', 'Doctor', 'Time', 'Status'],
            'entity' => 'appointment',
        ],
        'finance' => [
            'metaLabel' => 'Amount',
            'keywords' => ['finance', 'bank', 'banking', 'wallet', 'payment', 'fintech', 'invest', 'crypto', 'budget', 'expense', 'loan', 'money'],
            'brand' => ['Coinly', 'Ledgerly', 'Vaultr'],
            'tagline' => 'Your money, beautifully under control',
            'sub' => 'Send, save and invest from one secure account with real-time insights on every taka and dollar.',
            'cta' => 'Open free account',
            'nav' => ['Accounts', 'Cards', 'Invest', 'Business'],
            'features' => [['⚡', 'Instant transfers', 'Send money in seconds, 24/7.'], ['📊', 'Smart insights', 'See where every cent goes.'], ['🛡️', 'Bank-grade security', '2FA and encryption by default.']],
            'items' => [['Groceries', '-$54.20'], ['Salary', '+$2,400.00'], ['Electricity bill', '-$38.10'], ['Coffee', '-$4.50'], ['Freelance payout', '+$320.00'], ['Mobile recharge', '-$5.00']],
            'chips' => ['All', 'Income', 'Bills', 'Food', 'Transport', 'Savings'],
            'stats' => [['Balance', '$12,480', '+3.2%'], ['Spent this month', '$1,942', '-8%'], ['Savings', '$4,210', '+11%'], ['Investments', '$6,050', '+5.4%']],
            'tableHead' => ['Transaction', 'Category', 'Amount', 'Status'],
            'entity' => 'transaction',
        ],
        'education' => [
            'keywords' => ['education', 'course', 'learning', 'school', 'university', 'student', 'lms', 'tutor', 'exam', 'class'],
            'brand' => ['Learnly', 'Skillpath', 'Classnest'],
            'tagline' => 'Learn the skills that move you forward',
            'sub' => 'Bite-sized courses from expert instructors, with projects, quizzes and certificates.',
            'cta' => 'Browse courses',
            'nav' => ['Courses', 'Paths', 'Mentors', 'Pricing'],
            'features' => [['🎓', 'Expert instructors', 'Learn from industry pros.'], ['🧩', 'Hands-on projects', 'Build a real portfolio.'], ['📜', 'Certificates', 'Share proof of your skills.']],
            'items' => [['React from Zero to Pro', '$19'], ['Machine Learning Basics', '$24'], ['UI Design Fundamentals', '$15'], ['Laravel API Masterclass', '$22'], ['Data Structures in C++', '$18'], ['Spoken English', '$9']],
            'chips' => ['All', 'Development', 'Design', 'Data', 'Business', 'Language'],
            'stats' => [['Students', '8,214', '+14%'], ['Courses', '126', '+3'], ['Completion', '72%', '+5%'], ['Revenue', '$21.7k', '+9%']],
            'tableHead' => ['Student', 'Course', 'Progress', 'Status'],
            'entity' => 'enrollment',
        ],
        'travel' => [
            'keywords' => ['travel', 'hotel', 'booking', 'flight', 'trip', 'tour', 'airbnb', 'resort', 'vacation'],
            'brand' => ['Wandr', 'Roamio', 'Staybird'],
            'tagline' => 'Find your next escape',
            'sub' => 'Compare hotels, flights and tours with transparent prices and free cancellation.',
            'cta' => 'Explore stays',
            'nav' => ['Stays', 'Flights', 'Tours', 'Deals'],
            'features' => [['🏨', 'Handpicked stays', 'Rated by real travellers.'], ['💸', 'Best price', 'Price match on every booking.'], ['🔁', 'Free cancellation', 'Plans change — that\'s ok.']],
            'items' => [['Seaside Villa, Cox\'s Bazar', '$89/night'], ['Tea Garden Resort, Sylhet', '$64/night'], ['City Loft, Dhaka', '$42/night'], ['Hill Cabin, Bandarban', '$55/night'], ['Lagoon Hotel, Bali', '$120/night'], ['Old Town Inn, Lisbon', '$98/night']],
            'chips' => ['All', 'Beach', 'Mountains', 'City', 'Resorts', 'Cabins'],
            'stats' => [['Bookings', '612', '+21%'], ['Occupancy', '84%', '+6%'], ['Avg. rate', '$76', '+3%'], ['Reviews', '4.7', '+0.2']],
            'tableHead' => ['Guest', 'Property', 'Dates', 'Status'],
            'entity' => 'booking',
        ],
        'realestate' => [
            'keywords' => ['real estate', 'property', 'rent', 'apartment', 'house', 'home rental', 'flat', 'realtor'],
            'brand' => ['Homely', 'Nestfind', 'Keyhaven'],
            'tagline' => 'The right home is closer than you think',
            'sub' => 'Search verified rentals and homes for sale, book visits and talk to owners directly.',
            'cta' => 'Search homes',
            'nav' => ['Buy', 'Rent', 'Sell', 'Agents'],
            'features' => [['✅', 'Verified listings', 'Every listing is checked.'], ['📍', 'Map search', 'Find homes by neighbourhood.'], ['📅', 'Book visits', 'Schedule a tour in one tap.']],
            'items' => [['3-bed flat, Gulshan', '$950/mo'], ['2-bed apartment, Dhanmondi', '$520/mo'], ['Studio, Mirpur', '$210/mo'], ['Family house, Uttara', '$1,400/mo'], ['Penthouse, Banani', '$2,100/mo'], ['Duplex, Bashundhara', '$1,250/mo']],
            'chips' => ['All', 'Rent', 'Buy', 'Studio', 'Family', 'Luxury'],
            'stats' => [['Listings', '1,240', '+7%'], ['Visits booked', '318', '+12%'], ['Deals closed', '42', '+4'], ['Avg. rent', '$640', '+2%']],
            'tableHead' => ['Property', 'Client', 'Price', 'Status'],
            'entity' => 'listing',
        ],
        'fitness' => [
            'metaLabel' => 'Intensity',
            'keywords' => ['fitness', 'gym', 'workout', 'yoga', 'exercise', 'training', 'running', 'diet', 'wellness'],
            'brand' => ['Fitloop', 'Pacely', 'Motive'],
            'tagline' => 'Stronger every single week',
            'sub' => 'Personal workout plans, progress tracking and coaching that adapts to you.',
            'cta' => 'Start training',
            'nav' => ['Programs', 'Coaches', 'Classes', 'Pricing'],
            'features' => [['🏋️', 'Smart plans', 'Built around your goals.'], ['📈', 'Progress tracking', 'See every PR and streak.'], ['👟', 'Live classes', 'Train together, anywhere.']],
            'items' => [['Full Body HIIT — 25 min', 'Intense'], ['Morning Yoga Flow — 20 min', 'Easy'], ['Upper Body Strength — 40 min', 'Medium'], ['5K Running Plan — Week 3', 'Medium'], ['Core Crusher — 15 min', 'Intense'], ['Mobility & Stretch — 10 min', 'Easy']],
            'chips' => ['All', 'Strength', 'Cardio', 'Yoga', 'HIIT', 'Mobility'],
            'stats' => [['Workouts', '18', '+3'], ['Calories', '9,420', '+11%'], ['Streak', '12 days', '+4'], ['Weight', '71.2 kg', '-1.4']],
            'tableHead' => ['Member', 'Program', 'Sessions', 'Status'],
            'entity' => 'session',
        ],
        'agriculture' => [
            'keywords' => ['agri', 'agriculture', 'farm', 'farmer', 'crop', 'hydroponic', 'livestock', 'harvest', 'soil', 'fish'],
            'brand' => ['Krishok', 'Fieldly', 'Greenrow'],
            'tagline' => 'Smarter farming from seed to sale',
            'sub' => 'Monitor crops, get weather and disease alerts, and sell your harvest directly to buyers.',
            'cta' => 'Join as a farmer',
            'nav' => ['Market', 'Crop care', 'Weather', 'Experts'],
            'features' => [['🌱', 'Crop monitoring', 'Sensor data and growth tracking.'], ['🌦️', 'Weather alerts', 'Plan around rain and heat.'], ['🤝', 'Direct market', 'Sell without middlemen.']],
            'items' => [['Aman Rice — 40 kg', '$28'], ['Fresh Tomatoes — 10 kg', '$9'], ['Organic Lettuce — crate', '$7'], ['Hilsa Fish — 1 kg', '$14'], ['Urea Fertilizer — 50 kg', '$22'], ['Potato Seeds — 25 kg', '$17']],
            'chips' => ['All', 'Grains', 'Vegetables', 'Fish', 'Seeds', 'Fertilizer'],
            'stats' => [['Active fields', '24', '+2'], ['Soil moisture', '62%', '+4%'], ['Sales', '$3,180', '+15%'], ['Alerts', '3', '-2']],
            'tableHead' => ['Farmer', 'Crop', 'Quantity', 'Status'],
            'entity' => 'listing',
        ],
        'social' => [
            'metaLabel' => 'Posted',
            'keywords' => ['social', 'community', 'chat', 'messaging', 'forum', 'dating', 'network', 'friends', 'feed'],
            'brand' => ['Circlo', 'Huddle', 'Kinship'],
            'tagline' => 'Where your people hang out',
            'sub' => 'Share moments, join communities and chat with friends — without the noise.',
            'cta' => 'Join the community',
            'nav' => ['Feed', 'Groups', 'Events', 'Messages'],
            'features' => [['💬', 'Group chats', 'Fast, private and fun.'], ['🎉', 'Events', 'Meet up in real life.'], ['🧭', 'Discover', 'Find people who share your vibe.']],
            'items' => [['Nadia shared a photo from Sajek', '2m'], ['Photography Club: weekend walk', '15m'], ['Rafi started a poll', '1h'], ['Design Circle: portfolio review', '3h'], ['Mira is going to Tech Meetup', '5h'], ['Book Club: chapter 7 thread', '1d']],
            'chips' => ['For you', 'Following', 'Groups', 'Events', 'Trending', 'Nearby'],
            'stats' => [['Daily users', '24.1k', '+6%'], ['Posts', '3,920', '+12%'], ['Groups', '418', '+9'], ['Reports', '17', '-22%']],
            'tableHead' => ['User', 'Group', 'Activity', 'Status'],
            'entity' => 'post',
        ],
        'productivity' => [
            'metaLabel' => 'Due',
            'keywords' => ['task', 'todo', 'project', 'productivity', 'saas', 'crm', 'hr', 'team', 'notes', 'kanban', 'job', 'career', 'hiring', 'recruit', 'portal', 'admin'],
            'brand' => ['Taskflow', 'Planora', 'Workbench'],
            'tagline' => 'Ship more, stress less',
            'sub' => 'Plan projects, track tasks and keep your whole team in sync from one tidy workspace.',
            'cta' => 'Try it free',
            'nav' => ['Product', 'Solutions', 'Pricing', 'Docs'],
            'features' => [['🗂️', 'Boards & lists', 'Organize work your way.'], ['🔔', 'Smart reminders', 'Never miss a deadline.'], ['🔗', 'Integrations', 'Connect the tools you use.']],
            'items' => [['Redesign onboarding flow', 'Due Fri'], ['API rate-limit review', 'Due Mon'], ['Write Q4 roadmap', 'Due Wed'], ['Fix login bug #482', 'Today'], ['Client demo prep', 'Due Thu'], ['Update pricing page', 'Next week']],
            'chips' => ['All', 'To do', 'In progress', 'Review', 'Done', 'Mine'],
            'stats' => [['Open tasks', '146', '-9%'], ['Completed', '412', '+18%'], ['On-time rate', '91%', '+3%'], ['Team members', '24', '+2']],
            'tableHead' => ['Task', 'Owner', 'Due', 'Status'],
            'entity' => 'task',
        ],
        'services' => [
            'keywords' => ['laundry', 'dry clean', 'cleaning', 'ironing', 'salon', 'repair', 'plumber', 'electrician', 'maid', 'pickup', 'home service', 'services', 'car wash'],
            'brand' => ['Washly', 'Fixit', 'Tidyhub'],
            'tagline' => 'Chores done, without the hassle',
            'sub' => 'Book trusted local pros for laundry, cleaning and repairs. Pickup and delivery at a time that suits you.',
            'cta' => 'Book a pickup',
            'nav' => ['Services', 'Pricing', 'Track order', 'For business'],
            'features' => [['🧺', 'Doorstep pickup', 'We collect and return on your schedule.'], ['⏰', '48-hour turnaround', 'Express options when you need it fast.'], ['✅', 'Verified pros', 'Background-checked and rated.']],
            'items' => [['Wash & fold — 5 kg', '$6'], ['Dry cleaning — suit', '$8'], ['Ironing — 10 pieces', '$4'], ['Deep home cleaning', '$35'], ['AC servicing', '$18'], ['Curtain wash — set', '$12']],
            'chips' => ['All', 'Laundry', 'Dry clean', 'Ironing', 'Cleaning', 'Repairs'],
            'stats' => [['Orders today', '186', '+12%'], ['In progress', '42', '+5'], ['On-time rate', '97%', '+2%'], ['Revenue', '$2,940', '+9%']],
            'tableHead' => ['Order', 'Customer', 'Total', 'Status'],
            'entity' => 'order',
        ],
        'generic' => [
            'metaLabel' => 'Status',
            'keywords' => [],
            'brand' => ['Brightly', 'Nova', 'Elevate'],
            'tagline' => 'A better way to get things done',
            'sub' => 'Simple, fast and built for people who value their time.',
            'cta' => 'Get started',
            'nav' => ['Features', 'How it works', 'Pricing', 'About'],
            'features' => [['✨', 'Simple by design', 'No learning curve.'], ['⚡', 'Lightning fast', 'Built for speed.'], ['🔒', 'Private & secure', 'Your data stays yours.']],
            'items' => [['Getting started guide', 'New'], ['Weekly summary', 'Updated'], ['Team workspace', 'Active'], ['Integrations hub', 'Beta'], ['Mobile companion', 'Soon'], ['Analytics', 'Pro']],
            'chips' => ['All', 'Popular', 'New', 'Saved', 'Recent', 'Shared'],
            'stats' => [['Users', '12.4k', '+8%'], ['Sessions', '48.1k', '+5%'], ['Conversion', '3.9%', '+0.4%'], ['Churn', '1.2%', '-0.3%']],
            'tableHead' => ['Name', 'Type', 'Value', 'Status'],
            'entity' => 'item',
        ],
    ];

    public static function forPrompt(string $prompt, ?string $categorySlug = null): array
    {
        $slug = mb_strtolower((string) $categorySlug);
        $text = mb_strtolower($prompt).' '.str_replace('-', ' ', $slug);
        $best = 'generic';
        $bestScore = 0;

        foreach (self::PROFILES as $key => $profile) {
            $score = 0;
            foreach ($profile['keywords'] as $kw) {
                if (preg_match('/\\b'.preg_quote($kw, '/').'/u', $text)) {
                    $score += match (true) {
                        str_contains($kw, ' ') => 2,
                        in_array($kw, self::WEAK, true) => 0.5,  // shared across many domains
                        default => 1,
                    };
                }
            }
            if ($slug !== '' && str_contains(str_replace('-', '', $slug), $key)) {
                $score += 3;
            }
            if ($score > $bestScore) {
                $best = $key;
                $bestScore = $score;
            }
        }

        return ['key' => $best] + self::PROFILES[$best];
    }
}
