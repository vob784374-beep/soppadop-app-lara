export default function DashboardPage() {
  return (
    <div className="p-8">
      <h1 className="text-3xl font-bold mb-4">Admin Dashboard</h1>
      <p className="text-gray-600 dark:text-gray-400">
        Welcome to the admin dashboard. You are logged in.
      </p>
      <div className="mt-6">
        <a
          href="/"
          className="text-blue-600 hover:underline"
        >
          Back to Home
        </a>
      </div>
    </div>
  );
}
